<?php

namespace App\Models;

use App\Traits\AuthUser;
use App\Traits\DrawDetailsTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketOption extends Model
{
    use AuthUser,DrawDetailsTrait;

    protected $fillable = ['game_id', 'user_id', 'draw_detail_id', 'ticket_id', 'a_qty', 'b_qty', 'c_qty', 'number', 'option_id'];

    /**
     * Get the user that owns the TicketOption
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scopeForTicket(Builder $TicketOption, $ticket_id): Builder
    {
        return $TicketOption->where('ticket_id', $ticket_id);
    }

    public function totalCollection($total_qty)
    {
        return $total_qty * 11;
    }

    public function totalDistributions($total_qty)
    {
        return $total_qty * 100;
    }
    public function game()
{
    return $this->belongsTo(Game::class);
}

}
