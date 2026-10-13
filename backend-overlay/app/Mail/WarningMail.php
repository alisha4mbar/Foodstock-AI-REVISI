<?php

namespace App\Mail;

use App\Models\HasilForecast;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WarningMail extends Mailable
{
    public function __construct(public HasilForecast $forecast)
    {
    }

    public function envelope(): Envelope
    {
        $label = $this->forecast->status === 'shortage' ? 'Potensi Kekurangan (Shortage)' : 'Potensi Kelebihan (Surplus)';

        return new Envelope(subject: "[FoodStock AI] {$label}: {$this->forecast->komoditas->name} di {$this->forecast->wilayah->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.warning');
    }
}
