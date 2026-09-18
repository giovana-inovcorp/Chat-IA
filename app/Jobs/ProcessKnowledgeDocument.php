<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSegment;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessKnowledgeDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public KnowledgeDocument $document
    ) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $this->document->status = 'processing';
        $this->document->save();

        try {
            $fileContent = Storage::get($this->document->filename);
            $text = $this->extractText($fileContent, $this->document->mime_type);
            $chunks = $this->splitIntoSegments($text);

            foreach ($chunks as $position => $chunk) {
                $segment = KnowledgeSegment::create([
                    'document_id' => $this->document->id,
                    'content'     => $chunk,
                    'position'    => $position,
                ]);

                $embedding = $embeddingService->embed($chunk);

                $segment->update(['embedding' => json_encode($embedding)]);
            }

            $this->document->status = 'ready';
            $this->document->save();
            $this->document->collection->refreshStatus();
        } catch (\Throwable $e) {
            $this->document->status = 'error';
            $this->document->error_message = $e->getMessage();
            $this->document->save();
            $this->document->collection->refreshStatus();

            throw $e;
        }
    }

    private function extractText(string $content, string $mimeType): string
    {
        return match (true) {
            in_array($mimeType, ['text/plain', 'text/markdown']) => $content,

            $mimeType === 'application/pdf' => (function () use ($content) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($content);
                return $pdf->getText();
            })(),

            $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => (function () use ($content) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
                file_put_contents($tmpFile, $content);
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpFile);
                unlink($tmpFile);

                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                            foreach ($element->getElements() as $child) {
                                if (method_exists($child, 'getText')) {
                                    $text .= $child->getText();
                                }
                            }
                            $text .= "\n";
                        }
                    }
                }
                return $text;
            })(),

            default => throw new \RuntimeException("Unsupported mime type: {$mimeType}"),
        };
    }

    private function splitIntoSegments(string $text, int $chunkWords = 500, int $overlapWords = 50): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentWords = [];
        $currentCount = 0;

        foreach ($sentences as $sentence) {
            $words = explode(' ', $sentence);
            $wordCount = count($words);

            if ($currentCount + $wordCount > $chunkWords && $currentCount > 0) {
                $chunks[] = implode(' ', $currentWords);
                $overlap = array_slice($currentWords, -$overlapWords);
                $currentWords = array_merge($overlap, $words);
                $currentCount = count($currentWords);
            } else {
                $currentWords = array_merge($currentWords, $words);
                $currentCount += $wordCount;
            }
        }

        if (!empty($currentWords)) {
            $chunks[] = implode(' ', $currentWords);
        }

        return $chunks;
    }
}
