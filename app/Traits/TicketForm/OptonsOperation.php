<?php

namespace App\Traits\TicketForm;

use App\Models\DrawDetail;
use App\Models\Ticket;
use App\Models\TicketOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait OptonsOperation
{
    const PRICE = 11;

    public function move($focus, $row_property)
    {
        $this->calculateTotal($row_property);
        $this->dispatch($focus);
    }

    protected function filterOpenDrawIds(array $drawIds): array
    {
        // DB uses 24-hour format (HH:MM)
        $now = now('Asia/Kolkata')->format('H:i');

        \Log::debug('filterOpenDrawIds NOW', [
            'now'     => $now,
            'drawIds' => $drawIds
        ]);

        return \App\Models\DrawDetail::query()
            ->whereIn('id', $drawIds)
            ->whereRaw("STR_TO_DATE(end_time, '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$now])
            ->pluck('id')
            ->all();
    }

    public function keyTab($row_property)
    {
        $this->calculateTotal($row_property);
    }

    public function calculateTotal($row_property)
    {
        if ($this->{$row_property . '_qty'}) {
            $this->{'total_' . $row_property} = self::PRICE * ($this->{$row_property . '_qty'} * str()->length($this->{$row_property}));

            return $this->{'total_' . $row_property};
        }
    }

    public function enterKeyPressOnAbc()
    {
        $this->dispatch('focus-qty');
    }

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
        $this->abc_qty = $this->abc = '';
        $this->dispatch('focus-abc');

        $this->setStoreOptions($this->selected_draw);
    }

    public function keyEnter($row_property, $focus)
    {
        $total = $this->calculateTotal($row_property);
        if (
            $this->selected_draw &&
            $this->{$row_property} !== null &&
            $this->{$row_property} !== '' &&
            $this->{$row_property . '_qty'} !== null &&
            $this->{$row_property . '_qty'} !== '' &&
            $this->{$row_property . '_qty'} > 0
        ) {
            $options[] = $this->addOptions($this->{$row_property}, ucfirst($row_property), $this->{$row_property . '_qty'}, $total);
            $this->storeOptionsIntoCache($options);
            $this->dispatch($focus);
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
            'number'          => $number,
            'option'          => $option,
            'qty'             => $qty,
            'total'           => $total,
            'status'          => 'RUNNING',
            'created_at'      => Carbon::now(),
            'draw_details_ids' => $this->selected_draw,
        ];
    }

    public function storeOptionsIntoCache($data)
    {
        $options = collect($this->getOptionsIntoCache());
        if ($options) {
            $data = $options->merge($data);
        }

        return Cache::put('options', $data->values()->all(), 7200);
    }

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
        return collect(Cache::get('options'))->sortByDesc('created_at');
    }

    public function clearAllOptionsIntoCache()
    {
        Cache::forget('options');
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

    public function getActiveDrawIds(): array
    {
        $now = now('Asia/Kolkata')->format('H:i');

        return \App\Models\DrawDetail::where(function ($q) use ($now) {
            $q->where(function ($q1) use ($now) {
                $q1->whereRaw("STR_TO_DATE(start_time, '%H:%i') <= STR_TO_DATE(?, '%H:%i')", [$now])
                    ->whereRaw("STR_TO_DATE(end_time,   '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$now]);
            })->orWhereRaw("STR_TO_DATE(start_time, '%H:%i') > STR_TO_DATE(?, '%H:%i')", [$now]);
        })
            ->where('date', now('Asia/Kolkata')->toDateString())
            ->pluck('id')
            ->toArray();
    }

    public function submitTicket()
    {
        \Log::debug('submitTicket CLICK', [
            'game_id'        => $this->game_id ?? null,
            'selected_games' => $this->selected_games ?? null,
            'selected_draw'  => $this->selected_draw ?? null,
            'ticket_id'      => $this->current_ticket_id ?? null,
        ]);

        // Maintain backward compatibility: keep a single $this->game_id as the "primary" one
        if (!$this->game_id && !empty($this->selected_games)) {
            $this->game_id = (int) (is_array($this->selected_games)
                ? reset($this->selected_games)
                : $this->selected_games);
        }
        if (!$this->game_id && $this->current_ticket_id) {
            $this->game_id = \App\Models\Ticket::whereKey($this->current_ticket_id)->value('game_id');
        }
        if (!$this->game_id) {
            $this->addError('submit_error', 'Please select a Game (N1/N2) before submitting.');
            return true;
        }

        // Filter valid draws (still open)
        $openIds = $this->filterOpenDrawIds($this->selected_draw ?? []);

        if (empty($openIds)) {
            $this->addError('submit_error', 'Selected draw has closed. Please pick an upcoming draw.');
            \Log::debug('submitTicket ABORT: no valid draws');
            return true;
        }

        // Keep only valid IDs
        $digitMatrix = []; // Format: [digit][option] = count
        $selected_draw_ids = $this->selected_draw;
        $this->selected_draw = array_map('strval', $openIds);
        $selected_draw_ids   = array_map('intval', $openIds);

        // Normalize list of game IDs we should save for
        $gameIds = [];
        if (is_array($this->selected_games) && count($this->selected_games) > 0) {
            $gameIds = array_values(array_map('intval', $this->selected_games));
        } else {
            $gameIds = [(int) $this->game_id];
        }
        \Log::debug('submitTicket GAME IDS', ['gameIds' => $gameIds]);

        // dd($selected_draw_ids,$gameIds);
        return \DB::transaction(function () use ($selected_draw_ids, $gameIds) {
            $digitMatrix = [];

            // Create/update the single ticket (kept as-is)
            $this->current_ticket_id = \App\Models\Ticket::updateOrCreate(
                ['ticket_number' => $this->selected_ticket_number],
                [
                    'status'  => 'COMPLETED',
                    'user_id' => $this->auth_user->id,
                    'game_id' => $this->game_id, // keep primary game_id on ticket for compatibility
                ]
            )->id;

            // --- Minimal fix: populate ticket primary draw and primary game (non-destructive) ---
            try {
                // load ticket Eloquent model
                $ticket = \App\Models\Ticket::find($this->current_ticket_id);

                // Candidate first draw and game from selected arrays (these exist above)
                $firstDrawId = !empty($selected_draw_ids) ? (int) $selected_draw_ids[0] : null;
                $firstGameId = !empty($gameIds) ? (int) $gameIds[0] : null;

                // Fallback: if no first draw found (rare), try reading the first ticket_options just created
                if (empty($firstDrawId)) {
                    $firstOpt = \App\Models\TicketOption::where('ticket_id', $this->current_ticket_id)
                        ->orderBy('id')
                        ->first();
                    if ($firstOpt) {
                        $firstDrawId = (int) $firstOpt->draw_detail_id;
                    }
                }

                // Fallback: if firstGameId still empty, try derive from draw_details or ticket_options
                if (empty($firstGameId) && !empty($firstDrawId)) {
                    $draw = \App\Models\DrawDetail::find($firstDrawId);
                    if ($draw && !empty($draw->game_id)) {
                        $firstGameId = (int) $draw->game_id;
                    } else {
                        $optGame = \App\Models\TicketOption::where('ticket_id', $this->current_ticket_id)
                            ->where('draw_detail_id', $firstDrawId)
                            ->value('game_id');
                        if ($optGame) $firstGameId = (int) $optGame;
                    }
                }

                // Only update ticket columns if they are empty (non-destructive)
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

                // Optional: ensure draw_detail_game pivot contains all (draw,game) pairs selected.
                // This lets a draw be associated with multiple games going forward.
                if (!empty($selected_draw_ids) && !empty($gameIds) && \Schema::hasTable('draw_detail_game')) {
                    $insertRows = [];
                    foreach ($gameIds as $gid) {
                        foreach ($selected_draw_ids as $did) {
                            $insertRows[] = [
                                'draw_detail_id' => (int)$did,
                                'game_id'        => (int)$gid,
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ];
                        }
                    }
                    if (!empty($insertRows)) {
                        // insertOrIgnore in bulk (non-destructive)
                        \DB::table('draw_detail_game')->insertOrIgnore($insertRows);
                        // Test here
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Ticket post-create populate failed: ' . $e->getMessage());
                // Do not throw — keep original flow stable
            }


            if (count($this->getOptionsIntoCache()) == 0 && count($this->getCrossOptions()) == 0) {
                $this->addError('submit_error', 'Please add at least one entry!');
                return true;
            } else {
                $this->resetError();
            }

            $currentTime = now('Asia/Kolkata')->format('H:i');

            // Cleanups (by ticket/draw; independent of game)
            $drawIds = $this->getActiveDrawIds();
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

            // Save options for EACH game
            $storedOptions = $this->getOptionsIntoCache()->toArray();

            foreach ($gameIds as $gid) {
                foreach ($storedOptions as $option) {
                    $this->auth_user->options()->create([
                        'ticket_id'        => $this->current_ticket_id,
                        'game_id'          => $gid,
                        'draw_details_ids' => array_map('intval', array_values($option['draw_details_ids'])),
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

                        \App\Models\TicketOption::updateOrCreate(
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

            // Cross ABC (still uses current single $this->game_id; we’ll update these methods in next step)
            $this->saveCrossAbc();
            $this->saveCrossAbcDetail();

             // Recalculate totals on the selected draw_details
            $drawDetails = \App\Models\DrawDetail::whereIn('id', $selected_draw_ids)->get();
            $maximum_cross_amt = auth()->user()->maximum_cross_amount; 
            $maximum_tq = auth()->user()->maximum_tq; 
            foreach ($drawDetails as $detail) {
                $total_a_qty = $detail->ticketOptions->sum('a_qty') ?? 0;
                $total_b_qty = $detail->ticketOptions->sum('b_qty') ?? 0;
                $total_c_qty = $detail->ticketOptions->sum('c_qty') ?? 0;

                $total_qty = $total_a_qty + $total_b_qty + $total_c_qty;
                $total_cross_amt = $detail->crossAbcDetail->sum('amount') ?? 0;
                
                $errors = [];
                if ($total_qty > $maximum_tq) {
                    $errors['draw_detail_simple'] = "Draw exceeds allowed limit. (Qty: $total_qty)";
                }

                if ($total_cross_amt > $maximum_cross_amt) {
                    $errors['draw_detail_cross'] = "Draw exceeds allowed limit. (Cross Amt: $total_cross_amt)";
                }

                if (!empty($errors)) {
                    throw \Illuminate\Validation\ValidationException::withMessages($errors);
                }

                $detail->update([
                    'total_qty'       => $total_qty,
                    'total_cross_amt' => $total_cross_amt,
                ]);
            }

            // attach user->drawDetails
            $this->auth_user->drawDetails()->syncWithoutDetaching($selected_draw_ids);

            if (!$this->is_edit_mode) {
                $this->dispatch('refresh-window');
                $this->dispatch('ticketSubmitted');
            } else {
                return redirect()->route('dashboard');
            }

            return true;
        });
    }

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
