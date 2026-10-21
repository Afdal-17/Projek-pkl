<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua response (termasuk halaman error).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cegah MIME sniffing (mis. file gambar yang dieksekusi sebagai HTML/JS).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Cegah clickjacking: halaman hanya boleh di-frame oleh origin yang sama.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Bocoran referer diminimalkan ke origin tujuan saja.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Larang Flash/Adobe menetapkan kebijakan lintas domain.
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Matikan akses fitur perangkat yang tidak dipakai aplikasi.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS hanya dikirim saat sudah HTTPS (browser mengabaikannya di HTTP,
        // dan tidak berisiko mengunci akses localhost).
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
