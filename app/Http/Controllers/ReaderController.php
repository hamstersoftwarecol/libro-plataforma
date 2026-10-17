<?php

namespace App\Http\Controllers;

use App\Enums\BookFormat;
use App\Models\Book;
use App\Models\ReadingProgress;
use App\Services\AuditLogger;
use App\Services\Reader\BookContent;
use App\Services\Reader\ReadingSessionManager;
use App\Services\Reader\Watermarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReaderController extends Controller
{
    public const SESSION_HEADER = 'X-Reading-Session';

    public function __construct(private ReadingSessionManager $sessions, private BookContent $content) {}

    /**
     * Open the web reader (HU-011, HU-015, HU-016).
     */
    public function show(Request $request, Book $book, AuditLogger $auditLogger): View|RedirectResponse
    {
        if (Gate::denies('read', $book)) {
            abort_unless($book->isForSale() && $request->user()->is_active, 403);

            return redirect()->route('checkout.create', ['libro' => $book->slug])
                ->with('status', 'Compra «'.rtrim($book->title, '.').'» para leerlo. Es un pago único con acceso de por vida.');
        }

        $session = $this->sessions->start($request->user(), $book, $request);
        $progress = ReadingProgress::firstWhere(['user_id' => $request->user()->id, 'book_id' => $book->id]);

        $auditLogger->log('reader.opened', $book);

        return view('reader.show', [
            'book' => $book,
            'sessionToken' => $session->token,
            'position' => min(max(1, $progress?->position ?? 1), $book->unitCount()),
            'total' => $book->unitCount(),
            'pageWidths' => config('reader.page_widths'),
            'defaultWidth' => config('reader.default_page_width'),
        ]);
    }

    /**
     * Deliver one watermarked PDF page. Access is checked on every request (HU-013, HU-014).
     */
    public function page(Request $request, Book $book, int $page): Response|JsonResponse
    {
        if ($denied = $this->deny($request, $book, BookFormat::Pdf, $page)) {
            return $denied;
        }

        $validated = $request->validate([
            'w' => ['nullable', 'integer', Rule::in(config('reader.page_widths'))],
        ]);
        $width = (int) ($validated['w'] ?? config('reader.default_page_width'));

        try {
            $image = $this->content->pdfPage($book, $page, $width, $this->watermarkText($request));
        } catch (\Throwable $e) {
            Log::error("ReaderController: Error al renderizar página {$page} para libro {$book->id}: ".$e->getMessage());

            try {
                $fallbackPng = $this->content->fallbackPagePng($book, $page, $width);
                $image = app(Watermarker::class)->apply($fallbackPng, $this->watermarkText($request));
            } catch (\Throwable $e2) {
                Log::error("ReaderController: Falló incluso renderizado fallback para libro {$book->id}: ".$e2->getMessage());

                $image = base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==');
            }
        }

        return response($image, 200, $this->protectedHeaders('image/webp'));
    }

    /**
     * Deliver the text of one PDF page for translated reading. Same access checks as the image.
     */
    public function text(Request $request, Book $book, int $page): JsonResponse
    {
        if ($denied = $this->deny($request, $book, BookFormat::Pdf, $page)) {
            return $denied;
        }

        try {
            $html = $this->content->pdfPageText($book, $page);
        } catch (\Throwable $e) {
            Log::warning("Error al extraer texto para libro {$book->id} página {$page}: ".$e->getMessage());

            $html = '<p class="text-center opacity-60">(No se pudo extraer el texto de esta página. Cambia a modo página para ver el diseño original.)</p>';
        }

        return response()->json([
            'html' => $html,
            'watermark' => $this->watermarkText($request),
        ], 200, $this->protectedHeaders('application/json'));
    }

    /**
     * Deliver one sanitized EPUB chapter.
     */
    public function chapter(Request $request, Book $book, int $chapter): JsonResponse
    {
        if ($denied = $this->deny($request, $book, BookFormat::Epub, $chapter)) {
            return $denied;
        }

        return response()->json([
            'html' => $this->content->epubChapter($book, $chapter),
            'watermark' => $this->watermarkText($request),
        ], 200, $this->protectedHeaders('application/json'));
    }

    private function deny(Request $request, Book $book, BookFormat $format, int $unit): ?JsonResponse
    {
        if (Gate::denies('read', $book)) {
            return response()->json(['message' => 'Tu suscripción no está activa o el libro ya no está disponible.'], 403);
        }

        if (! $this->sessions->touch($request->user(), $book, $request->header(self::SESSION_HEADER))) {
            return response()->json(['message' => 'Abriste este u otro libro en otro dispositivo o pestaña. Esta sesión de lectura se cerró.'], 409);
        }

        if ($book->file_format !== $format || $unit < 1 || $unit > $book->unitCount()) {
            return response()->json(['message' => 'Página fuera de rango.'], 404);
        }

        return null;
    }

    private function watermarkText(Request $request): string
    {
        return $request->user()->email.' | '.now()->format('Y-m-d H:i');
    }

    /**
     * @return array<string, string>
     */
    private function protectedHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, noarchive',
        ];
    }
}
