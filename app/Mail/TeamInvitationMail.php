<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $company;
    public $isCustomPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $password, bool $isCustomPassword = false)
    {
        $this->user = $user;
        $this->password = $password;
        $this->company = $user->company;
        $this->isCustomPassword = $isCustomPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . $this->company->name . ' - Your Team Invitation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'user' => $this->user,
                'password' => $this->password,
                'company' => $this->company,
                'isCustomPassword' => $this->isCustomPassword,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
} 