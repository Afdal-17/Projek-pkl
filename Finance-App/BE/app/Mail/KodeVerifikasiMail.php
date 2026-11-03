<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KodeVerifikasiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaPenerima,
        public string $kode,
        public int $menitBerlaku,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi ' . config('app.name', 'Finance App'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
