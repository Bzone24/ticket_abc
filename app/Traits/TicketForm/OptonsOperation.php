<?php

namespace App\Traits\TicketForm;

use App\Models\DrawDetail;
use App\Models\Ticket;
use App\Models\TicketOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait OptonsOperation
{
    const PRICE = 11;

    public function move($focus, $row_property)
    {
        $this->calculateTotal($row_property);
        $this->dispatch($focus);
    }

    /**
     * Returns only draw ids that are still open (end_time >= now)
     */
    protected function filterOpenDrawIds(array $drawIds): array
    {
        // DB uses 24-hour format (HH:MM)
        $now = now('Asia/Kolkata')->format('H:i');

        if (empty($drawIds)) {
            return [];
        }

        return DrawDetail::query()
            ->whereIn('id', $drawIds)
            ->whereRaw("STR_TO_DATE(end_time, '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$now])
            ->pluck('id')
            ->all();
    }

    public function keyTab($row_property)
    {
        $this->calculateTotal($row_property);
    }

    /**
     * Calculate total for a row property (keeps original formula)
     */
    public function calculateTotal($row_property)
    {
        $qtyProp = $row_property . '_qty';
        $totalProp = 'total_' . $row_property;

        if (!empty($this->{$qtyProp})) {
            // keep str()->length to preserve behaviour (multibyte aware in Laravel)
            $this->{$totalProp} = self::PRICE * ($this->{$qtyProp} * str()->length($this->{$row_property}));
            return $this->{$totalProp};
        }

        // Explicitly return null as before (original returned null if no qty)
        return null;
    }

    public function enterKeyPressOnAbc()
    {
        $this->dispatch('focus-qty');
    }

    /**
     * Handle Enter on qty: validate, create 3 option rows (A,B,C) and cache them
     */
    public function enterKeyPressOnQty()
    {
        $hasError = false;

        if (empty($this->abc)) {
            $this->addError('abc', 'Please Enter The Value');
            $hasError = true;
        }

        if (empty($this->abc_qty)) {
            $this->addError('abc_qty', 'Please Enter Qty');
            $hasError = true;
        } elseif ($this->abc_qty <= 0) {
            $this->addError('abc_qty', 'Qty must be greater than 0');
            $hasError = true;
        }

        if ($hasError) {
            return true;
        }

        $this->resetError();

        $total = $this->abc_qty * str()->length($this->abc) * self::PRICE;

        $options = [];
        foreach (['A', 'B', 'C'] as $option) {
            $options[] = $this->addOptions($this->abc, $option, $this->abc_qty, $total);
        }

        $this->storeOptionsIntoCache($options);

        // reset quickly
        $this->abc_qty = $this->abc = '';
        $this->dispatch('focus-abc');

        $this->setStoreOptions($this->selected_draw);
    }

    /**
     * Generic key enter for individual row properties
     */
    public function keyEnter($row_property, $focus)
    {
        $total = $this->calculateTotal($row_property);

        $val = $this->{$row_property} ?? null;
        $qty = $this->{$row_property . '_qty'} ?? null;

        if (
            $this->selected_draw &&
            $val !== null && $val !== '' &&
            $qty !== null && $qty !== '' &&
            $qty > 0
        ) {
            $options = [];
            $options[] = $this->addOptions($val, ucfirst($row_property), $qty, $total);
            $this->storeOptionsIntoCache($options);
            $this->dispatch($focus);

            // reset fields
            $this->{$row_property} = '';
            $this->{$row_property . '_qty'} = '';
            $this->{'total_' . $row_property} = 0;
            $this->resetError();
        }
        $this->setStoreOptions($this->selected_draw);
    }

    public function addOptions($number, $option, $qty, $total)
    {
        return [
            'number'           => $number,
            'option'           => $option,
            'qty'              => $qty,
            'total'            => $total,
            'status'           => 'RUNNING',
            'created_at'       => Carbon::now(),
            'draw_details_ids' => $this->selected_draw,
        ];
    }

    /**
     * Store provided options into cache. Preserves original merge order:
     * existing options first, then incoming appended.
     */
    public function storeOptionsIntoCache($data)
    {
        $existing = $this->getOptionsIntoCache()->values()->all();
        $incoming = is_array($data) ? $data : ( $data instanceof Collection ? $data->values()->all() : (array) $data );

        // merge existing first so new entries appear after existing (same as original merge)
        $merged = array_values(array_merge($existing, $incoming));

        Cache::put('options', $merged, 7200);

        return true;
    }

    /**
     * Store options collection directly (keeps original shape)
     */
    public function optionStoreToCache(Collection $data)
    {
        Cache::put('options', $data->values()->all(), 7200);
    }

    public function deleteOption($index)
    {
        $data = collect($this->getOptionsIntoCache())->values();
        $data->forget($index);
        Cache::put('options', $data->values()->all());
        $this->loadOptions(true);
    }

    public function getOptionsIntoCache()
    {
        return collect(Cache::get('options', []))->sortByDesc('created_at')->values();
    }

    public function clearAllOptionsIntoCache()
    {
        Cache::forget('options');

        if (property_exists($this, 'stored_options')) {
            $this->stored_options = [];
        }

        if (property_exists($this, 'final_total_qty')) {
            $this->final_total_qty = 0;
        }
        if (property_exists($this, 'cross_final_total_qty')) {
            $this->cross_final_total_qty = 0;
        }

        if (method_exists($this, 'loadOptions')) {
            $this->loadOptions(true);
        }

        if (method_exists($this, 'calculateFinalTotal')) {
            $this->calculateFinalTotal();
        }

        // dispatch browser event (safe in any Livewire version)
        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('optionsCleared');
        }
    }

    public function clearDisplaySlip()
    {
        $this->clearAllOptionsIntoCache();
        Cache::forget('cross_abc_data');

        $this->loadOptions(true);
        $this->stored_cross_abc_data = [];

        $this->final_total_qty = 0;
        $this->cross_final_total_qty = 0;
    }

    public function resetError()
    {
        $this->resetErrorBag(['abc', 'abc_qty', 'submit_error']);
    }

    /**
     * Get active draw ids (open or upcoming) for today
     */
    public function getActiveDrawIds(): array
    {
        $now = now('Asia/Kolkata')->format('H:i');

        return DrawDetail::where(function ($q) use ($now) {
            $q->where(function ($q1) use ($now) {
                $q1->whereRaw("STR_TO_DATE(start_time, '%H:%i') <= STR_TO_DATE(?, '%H:%i')", [$now])
                    ->whereRaw("STR_TO_DATE(end_time,   '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$now]);
            })->orWhereRaw("STR_TO_DATE(start_time, '%H:%i') > STR_TO_DATE(?, '%H:%i')", [$now]);
        })
        ->where('date', now('Asia/Kolkata')->toDateString())
        ->pluck('id')
        ->toArray();
    }

    /**
     * submitTicket - optimized internals but NO behavior changes and no logging
     */
    public function submitTicket()
    {
        // Maintain backward compatibility: keep a single $this->game_id as the "primary" one
        if (!$this->game_id && !empty($this->selected_games)) {
            $this->game_id = (int) (is_array($this->selected_games) ? reset($this->selected_games) : $this->selected_games);
        }
        if (!$this->game_id && $this->current_ticket_id) {
            $this->game_id = Ticket::whereKey($this->current_ticket_id)->value('game_id');
        }
        if (!$this->game_id) {
            $this->addError('submit_error', 'Please select a Game (N1/N2) before submitting.');
            return true;
        }

        // Filter valid draws (still open)
        $openIds = $this->filterOpenDrawIds($this->selected_draw ?? []);
        if (empty($openIds)) {
            $this->addError('submit_error', 'Selected draw has closed. Please pick an upcoming draw.');
            return true;
        }

        // Normalize selected draws and game ids
        $selected_draw_ids = array_map('intval', $openIds);
        $this->selected_draw = array_map('strval', $openIds);

        $gameIds = [];
        if (is_array($this->selected_games) && count($this->selected_games) > 0) {
            $gameIds = array_values(array_map('intval', $this->selected_games));
        } else {
            $gameIds = [(int) $this->game_id];
        }

        return DB::transaction(function () use ($selected_draw_ids, $gameIds) {
            $digitMatrix = [];

            // Create or update the ticket (kept as-is)
            $this->current_ticket_id = Ticket::updateOrCreate(
                ['ticket_number' => $this->selected_ticket_number],
                [
                    'status'  => 'COMPLETED',
                    'user_id' => $this->auth_user->id,
                    'game_id' => $this->game_id,
                ]
            )->id;

            // Populate ticket primary draw and primary game (non-destructive)
            try {
                $ticket = Ticket::find($this->current_ticket_id);

                $firstDrawId = !empty($selected_draw_ids) ? (int) $selected_draw_ids[0] : null;
                $firstGameId = !empty($gameIds) ? (int) $gameIds[0] : null;

                if (empty($firstDrawId)) {
                    $firstOpt = TicketOption::where('ticket_id', $this->current_ticket_id)
                        ->orderBy('id')
                        ->first();
                    if ($firstOpt) {
                        $firstDrawId = (int) $firstOpt->draw_detail_id;
                    }
                }

                if (empty($firstGameId) && !empty($firstDrawId)) {
                    $draw = DrawDetail::find($firstDrawId);
                    if ($draw && !empty($draw->game_id)) {
                        $firstGameId = (int) $draw->game_id;
                    } else {
                        $optGame = TicketOption::where('ticket_id', $this->current_ticket_id)
                            ->where('draw_detail_id', $firstDrawId)
                            ->value('game_id');
                        if ($optGame) $firstGameId = (int) $optGame;
                    }
                }

                $update = [];
                if ($ticket && empty($ticket->draw_detail_id) && !empty($firstDrawId)) {
                    $update['draw_detail_id'] = $firstDrawId;
                }
                if ($ticket && empty($ticket->game_id) && !empty($firstGameId)) {
                    $update['game_id'] = $firstGameId;
                }
                if (!empty($update)) {
                    $ticket->update($update);
                }

                if (!empty($selected_draw_ids) && !empty($gameIds) && Schema::hasTable('draw_detail_game')) {
                    $insertRows = [];
                    foreach ($gameIds as $gid) {
                        foreach ($selected_draw_ids as $did) {
                            $insertRows[] = [
                                'draw_detail_id' => (int) $did,
                                'game_id'        => (int) $gid,
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ];
                        }
                    }
                    if (!empty($insertRows)) {
                        DB::table('draw_detail_game')->insertOrIgnore($insertRows);
                    }
                }
            } catch (\Throwable $e) {
                // swallowing to preserve existing behavior (no logging)
            }

            // Ensure there is at least one entry to submit
            $cachedOptions = $this->getOptionsIntoCache();
            $cachedCross   = $this->getCrossOptions();

            if ($cachedOptions->count() == 0 && $cachedCross->count() == 0) {
                $this->addError('submit_error', 'Please add at least one entry!');
                return true;
            } else {
                $this->resetError();
            }

            $currentTime = now('Asia/Kolkata')->format('H:i');

            // Cleanups (by ticket/draw; independent of game)
            $drawIds = $this->getActiveDrawIds();

            // Delete options for ticket where draw_details_ids contains an open draw id
            $this->auth_user->options()
                ->where('ticket_id', $this->current_ticket_id)
                ->where(function ($query) use ($drawIds) {
                    foreach ($drawIds as $id) {
                        $query->orWhereJsonContains('draw_details_ids', $id);
                    }
                })
                ->delete();

            $this->auth_user->ticketOptions()
                ->where('ticket_id', $this->current_ticket_id)
                ->whereHas('DrawDetail', function ($query) use ($currentTime) {
                    $query->where(function ($q) use ($currentTime) {
                        $q->where(function ($q1) use ($currentTime) {
                            $q1->whereRaw("STR_TO_DATE(start_time, '%H:%i') <= STR_TO_DATE(?, '%H:%i')", [$currentTime])
                                ->whereRaw("STR_TO_DATE(end_time,   '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$currentTime]);
                        })->orWhereRaw("STR_TO_DATE(start_time, '%H:%i') > STR_TO_DATE(?, '%H:%i')", [$currentTime]);
                    });
                })
                ->delete();

            $this->auth_user->crossAbc()
                ->where('ticket_id', $this->current_ticket_id)
                ->where(function ($query) use ($drawIds) {
                    foreach ($drawIds as $id) {
                        $query->orWhereJsonContains('draw_details_ids', $id);
                    }
                })
                ->delete();

            $this->auth_user->crossAbcDetail()
                ->where('ticket_id', $this->current_ticket_id)
                ->whereHas('drawDetail', function ($query) use ($currentTime) {
                    $query->where(function ($q) use ($currentTime) {
                        $q->where(function ($q1) use ($currentTime) {
                            $q1->whereRaw("STR_TO_DATE(start_time, '%H:%i') <= STR_TO_DATE(?, '%H:%i')", [$currentTime])
                                ->whereRaw("STR_TO_DATE(end_time,   '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$currentTime]);
                        })->orWhereRaw("STR_TO_DATE(start_time, '%H:%i') > STR_TO_DATE(?, '%H:%i')", [$currentTime]);
                    });
                })
                ->delete();

            // Save options for EACH game (keep create() to preserve Eloquent events)
            $storedOptions = $cachedOptions->values()->all();

            foreach ($gameIds as $gid) {
                foreach ($storedOptions as $option) {
                    $this->auth_user->options()->create([
                        'ticket_id'        => $this->current_ticket_id,
                        'game_id'          => $gid,
                        'draw_details_ids' => array_map('intval', array_values($option['draw_details_ids'] ?? [])),
                        'number'           => $option['number'],
                        'option'           => $option['option'],
                        'qty'              => $option['qty'],
                        'total'            => $option['total'],
                        'status'           => 'COMPLETED',
                    ]);
                }
            }

            // Build digit matrix once from cached options
            $digitMatrix = [];
            foreach ($storedOptions as $opt) {
                $option = $opt['option'];
                $digits = str_split((string) $opt['number']);
                $qty    = $opt['qty'];
                foreach ($digits as $digit) {
                    if (!isset($digitMatrix[$digit][$option])) {
                        $digitMatrix[$digit][$option] = 0;
                    }
                    $digitMatrix[$digit][$option] += $qty;
                }
            }
            ksort($digitMatrix);

            // Save TicketOption for EACH game and draw
            foreach ($gameIds as $gid) {
                foreach ($selected_draw_ids as $draw_detail_id) {
                    foreach ($digitMatrix as $number => $opts) {
                        $a = $opts['A'] ?? 0;
                        $b = $opts['B'] ?? 0;
                        $c = $opts['C'] ?? 0;

                        TicketOption::updateOrCreate(
                            [
                                'user_id'        => $this->auth_user->id,
                                'game_id'        => $gid,
                                'draw_detail_id' => $draw_detail_id,
                                'ticket_id'      => $this->current_ticket_id,
                                'number'         => $number,
                            ],
                            [
                                'a_qty' => $a,
                                'b_qty' => $b,
                                'c_qty' => $c,
                            ]
                        );
                    }
                }
            }

            // Cross ABC persistence (kept as-is)
            $this->saveCrossAbc();
            $this->saveCrossAbcDetail();

            // Recalculate totals on the selected draw_details
            $drawDetails = \App\Models\DrawDetail::whereIn('id', $selected_draw_ids)->get();
            $maximum_cross_amt = auth()->user()->maximum_cross_amount; 
            $maximum_tq = auth()->user()->maximum_tq; 
            $drawDetails = DrawDetail::whereIn('id', $selected_draw_ids)->get();
            foreach ($drawDetails as $detail) {
                $total_a_qty = $detail->ticketOptions->sum('a_qty') ?? 0;
                $total_b_qty = $detail->ticketOptions->sum('b_qty') ?? 0;
                $total_c_qty = $detail->ticketOptions->sum('c_qty') ?? 0;

                $total_qty = $total_a_qty + $total_b_qty + $total_c_qty;
                $total_cross_amt = $detail->crossAbcDetail->sum('amount') ?? 0;
                
                if ($total_qty > $maximum_tq) {
                    $this->dispatch('swal', [
                        'icon'  => 'error',
                        'title' => 'Oops!',
                        'text'  => "Draw exceeds allowed limit. (TQ qty: $total_qty)",
                    ]);
                    return;
                }

                if ($total_cross_amt > $maximum_cross_amt) {
                    $this->dispatch('swal', [
                        'icon'  => 'error',
                        'title' => 'Oops!',
                        'text'  => "Draw exceeds allowed limit. (Cross Amt: $total_cross_amt)",
                    ]);
                    return;
                }


                $detail->update([
                    'total_qty'       => $total_qty,
                    'total_cross_amt' => $total_cross_amt,
                ]);
            }

            // attach user->drawDetails
            $this->auth_user->drawDetails()->syncWithoutDetaching($selected_draw_ids);

            // -------------------- SAFE: build & emit payload for frontend printing (non-destructive) --------------------
            try {
                $payloadStoredOptions = $cachedOptions->values()->all();
            } catch (\Throwable $e) {
                $payloadStoredOptions = [];
            }

            try {
                $totalForCalc = collect($payloadStoredOptions)->sum('total');

                if (method_exists($this, 'calculateTq')) {
                    $tq = $this->calculateTq();
                } else {
                    $tq = $totalForCalc > 0 ? (int) floor($totalForCalc / self::PRICE) : 0;
                }

                $total = $totalForCalc;
                $drawCount = is_countable($this->selected_draw) ? count($this->selected_draw) : 1;

                if (method_exists($this, 'calculateFinalTotal')) {
                    $finalTotal = $this->calculateFinalTotal();
                } else {
                    $finalTotal = $total * max(1, $drawCount);
                }

                $labels = $this->selected_game_labels ?? $this->selected_games ?? [];
                $times = is_array($this->selected_times) ? $this->selected_times : ($this->selected_times ? [$this->selected_times] : []);

                $payload = [
                    'ticket_number'  => $this->selected_ticket_number ?? ($this->selected_ticket->ticket_number ?? null),
                    'stored_options' => $payloadStoredOptions,
                    'tq'             => $tq,
                    'total'          => $total,
                    'finalTotal'     => $finalTotal,
                    'draw_count'     => $drawCount,
                    'labels'         => $labels,
                    'times'          => $times,
                ];

                try {
                    $this->emit('ticketSubmitted', $payload);
                } catch (\Throwable $e) {
                    // swallowing emit failures
                }
            } catch (\Throwable $e) {
                // swallowing payload build failures
            }

            if (!$this->is_edit_mode) {
                $this->dispatch('refresh-window');
                $this->dispatch('ticketSubmitted');
            } else {
                return redirect()->route('dashboard');
            }

            return true;
        });
    }

    /**
     * Write back options to cache, mark status completed and refresh UI
     */
    public function setStoreOptions(array $selected_draw_ids): void
    {
        $options = $this->getOptionsIntoCache()
            ->map(function ($store_option) use ($selected_draw_ids) {
                $store_option['draw_details_ids'] = $selected_draw_ids;
                $store_option['status'] = 'COMPLETED';
                return $store_option;
            })
            ->values()
            ->all();

        Cache::put('options', $options, 7200);
        $this->loadOptions(true);
        $this->calculateFinalTotal();
    }
}
