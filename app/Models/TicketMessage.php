<?php

namespace App\Models;

use App\Enums\TicketMessageType;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property TicketMessageType $type
 * @property string $body
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User $author
 */
#[Fillable(['ticket_id', 'user_id', 'type', 'body', 'is_internal'])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketMessageType::class,
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Determine whether this is an automatic log entry rather than something a person wrote.
     */
    public function isLog(): bool
    {
        return $this->type === TicketMessageType::Log;
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who wrote the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Scope a query to public replies, leaving out internal notes.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function public(Builder $query): void
    {
        $query->where('is_internal', false);
    }
}
