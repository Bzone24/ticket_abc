<?php

namespace App\Traits\TicketForm;

use App\Models\CrossAbc;
use App\Models\CrossAbcDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CrossAbcOperation
{
    public $cross_abc_input;
    public $cross_abc_amt;
    public $cross_combination = 3;
    public $activeTab = 'simple_abc'; // default tab
    public $cross_ab;
    public $cross_ab_amt;
    public $cross_ac;
    public $cross_ac_amt;
    public $cross_bc;
    public $cross_bc_amt;
    public $cross_a;
    public $cross_b;
    public $cross_c;
    public $cross_single_amount;
    public $cross_attributes = [
        'cross_abc_input' => 'ABC',
        'cross_abc_amt' => 'Qty',
        'cross_combination' => 'Combination',
    ];

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function resetCrossError()
    {
        $this->resetErrorBag(['cross_abc_input', 'cross_abc_amt', 'cross_combination']);
    }

    public function resetAbError()
    {
        $this->resetErrorBag(['cross_ab_amt', 'cross_ab']);
    }

    /**
     * Generate combinations for ABC input.
     * Returns array: [ab, ac, bc]
     */
    public function makeCombination(): array
    {
        $cross_abc = (string) $this->cross_abc_input;
        $input_combination = (int) $this->cross_combination;
        $chars = str_split($cross_abc);

        // If length not at least 2, return zeros to avoid errors.
        if (count($chars) < 2) {
            return [0, 0, 0];
        }

        // Quick path: when exactly 3 digits and combination == 3 -> pair digits
        if ($input_combination === 3 && count($chars) === 3) {
            $ab = (int) ($chars[0] . $chars[1]);
            $ac = (int) ($chars[0] . $chars[2]);
            $bc = (int) ($chars[1] . $chars[2]);
            return [$ab, $ac, $bc];
        }

        // When combination == 27 (full cartesian with repetition)
        if ($input_combination === 27 && count($chars) === 3) {
            $keys = ['ab', 'ac', 'bc'];
            $result = [];
            foreach ($keys as $key) {
                $combis = [];
                foreach ($chars as $first) {
                    foreach ($chars as $second) {
                        $combis[] = (int) ($first . $second);
                    }
                }
                $result[$key] = $combis;
            }
            return [$result['ab'], $result['ac'], $result['bc']];
        }

        // If combination == 27 but length == 2 (edge-case in your original code)
        if ($input_combination === 27 && count($chars) === 2) {
            $value = (int) ($chars[0] . $chars[1]);
            return [[$value], [$value], [$value]];
        }

        // Fallback: return basic AB pair (prevents undefined index)
        $ab = (int) ($chars[0] . ($chars[1] ?? $chars[0]));
        $ac = (int) ($chars[0] . ($chars[2] ?? ($chars[1] ?? $chars[0])));
        $bc = (int) (($chars[1] ?? $chars[0]) . ($chars[2] ?? $chars[1] ?? $chars[0]));
        return [$ab, $ac, $bc];
    }

    /**
     * Normalize add option payload
     */
    public function addCrossOptions($amt, $comb, $number = null, $ab = null, $ac = null, $bc = null, $option = null)
    {
        return [
            'number' => $number,
            'ab' => $ab,
            'ac' => $ac,
            'bc' => $bc,
            'amt' => $amt,
            'combination' => $comb,
            'option' => $option,
            'created_at' => Carbon::now(),
            'draw_details_ids' => $this->selected_draw,
        ];
    }

    /**
     * Store into cache. Accepts array or collection.
     */
    public function storeCrossAbcIntoCache($data)
    {
        // Ensure incoming data is an array of items
        $incoming = is_array($data) ? $data : ( $data instanceof \Illuminate\Support\Collection ? $data->all() : (array) $data );

        // Get existing safely once
        $existing = Cache::get('cross_abc', []);

        // Merge (incoming might be list with numeric keys)
        $merged = array_values(array_merge($incoming, $existing));

        // Put merged back (2 hours)
        Cache::put('cross_abc', $merged, 7200);

        // Refresh cached UI datasets
        $this->loadAbcData(true);
    }

    public function getCrossOptions()
    {
        // Always return a collection (sorted) and default to empty array
        return collect(Cache::get('cross_abc', []))->sortByDesc('created_at')->values();
    }

    public function deleteCrossAbc($index)
    {
        $data = collect($this->getCrossOptions())->values();
        $data->forget($index);
        Cache::put('cross_abc', $data->values()->all());
        $this->loadAbcData(true);
    }

    public function clearAllCrossAbcIntoCache()
    {
        Cache::forget('cross_abc');
        $this->loadAbcData(true);
    }

    /**
     * Enter Abc of cross - optimized validations & logic grouping
     */
    public function enterKeyPressOnCrossAbc($focus, $value)
    {
        // Build rules once
        $isThreeLen = strlen((string) $this->cross_abc_input) == 3;

        $rules = [
            'cross_abc_input'  => ['required', 'regex:/^[0-9]{1,3}$/'],
            'cross_abc_amt'    => ['required', 'integer', 'multiple_of:5'],
            'cross_combination' => $isThreeLen ? ['required', 'in:3,27'] : ['required', 'in:3'],
        ];

        $attributes = [
            'cross_abc_input' => 'ABC',
            'cross_abc_amt' => 'Amount',
            'cross_combination' => 'Combination',
        ];

        if ($value === 'cross_abc_input') {
            $this->validate(['cross_abc_input' => $rules['cross_abc_input']], [], ['cross_abc_input' => $attributes['cross_abc_input']]);
            $this->dispatch($focus);
            return;
        }

        if ($value === 'cross_abc_amt') {
            // validate both abc and amount for safety
            $this->validate([
                'cross_abc_input' => $rules['cross_abc_input'],
                'cross_abc_amt' => $rules['cross_abc_amt'],
            ], [], [
                'cross_abc_input' => $attributes['cross_abc_input'],
                'cross_abc_amt' => $attributes['cross_abc_amt'],
            ]);
            $this->dispatch($focus);
            return;
        }

        if ($value === 'cross_combination') {
            // validate all three in one call (faster than 3 separate validate calls)
            $this->validate($rules, [], $attributes);

            // Generate combinations once
            [$ab, $ac, $bc] = $this->makeCombination();

            $payload = [];
            $payload[] = $this->addCrossOptions(
                amt: $this->cross_abc_amt,
                comb: $this->cross_combination,
                number: $this->cross_abc_input,
                ab: $ab,
                ac: $ac,
                bc: $bc,
                option: 'ABC'
            );

            $this->storeCrossAbcIntoCache($payload);

            // clear inputs quickly
            $this->cross_abc_input = $this->cross_abc_amt = $this->cross_combination = '';
            $this->resetCrossError();

            // Add tickets/draw details (keeps behavior)
            $this->AddTicketAndDrawDetailsIntoCrossAbc($this->selected_draw);

            // move focus
            $this->dispatch($focus);
        }
    }

    /**
     * Enter AB optimized
     */
    public function enterKeyPressOnCrossAb($focus, $value)
    {
        if ($value === 'cross_ab') {
            $this->validate(['cross_ab' => ['required', 'regex:/^[0-9]{2}$/']], [], ['cross_ab' => 'AB']);
            $this->dispatch($focus);
            return;
        }

        if ($value === 'cross_ab_amt') {
            // Validate both together to reduce calls
            $this->validate([
                'cross_ab' => ['required', 'regex:/^[0-9]{2}$/'],
                'cross_ab_amt' => ['required', 'integer', 'min:1'],
            ], [], ['cross_ab' => 'AB', 'cross_ab_amt' => 'Amount']);

            $payload[] = $this->addCrossOptions(
                ab: $this->cross_ab,
                amt: $this->cross_ab_amt,
                comb: 1,
                number: $this->cross_ab,
                option: 'AB'
            );

            $this->storeCrossAbcIntoCache($payload);

            $this->cross_ab = $this->cross_ab_amt = '';
            $this->AddTicketAndDrawDetailsIntoCrossAbc($this->selected_draw);
            $this->dispatch($focus);
        }
    }

    /**
     * Enter AC optimized
     */
    public function enterKeyPressOnCrossAc($focus, $value)
    {
        if ($value === 'cross_ac') {
            $this->validate(['cross_ac' => ['required', 'regex:/^[0-9]{2}$/']], [], ['cross_ac' => 'AC']);
            $this->dispatch($focus);
            return;
        }

        if ($value === 'cross_ac_amt') {
            $this->validate([
                'cross_ac' => ['required', 'regex:/^[0-9]{2}$/'],
                'cross_ac_amt' => ['required', 'integer', 'min:1'],
            ], [], ['cross_ac' => 'AC', 'cross_ac_amt' => 'Amount']);

            $payload[] = $this->addCrossOptions(
                ac: $this->cross_ac,
                amt: $this->cross_ac_amt,
                comb: 1,
                number: $this->cross_ac,
                option: 'AC'
            );

            $this->storeCrossAbcIntoCache($payload);

            $this->cross_ac = $this->cross_ac_amt = '';
            $this->AddTicketAndDrawDetailsIntoCrossAbc($this->selected_draw);
            $this->dispatch($focus);
        }
    }

    /**
     * Generate regular combinations for A/B/C inputs (optimized)
     * returns [ab, ac, bc, total]
     */
    public function generateRegularCombinations()
    {
        $a = (string) $this->cross_a;
        $b = (string) $this->cross_b;
        $c = (string) $this->cross_c;

        $aDigits = $a !== '' ? str_split($a) : [];
        $bDigits = $b !== '' ? str_split($b) : [];
        $cDigits = $c !== '' ? str_split($c) : [];

        $makePairs = function ($x, $y) {
            $pairs = [];
            foreach ($x as $dx) {
                foreach ($y as $dy) {
                    $pairs[] = (int) ($dx . $dy);
                }
            }
            return $pairs;
        };

        $ab = (!empty($aDigits) && !empty($bDigits)) ? $makePairs($aDigits, $bDigits) : [];
        $ac = (!empty($aDigits) && !empty($cDigits)) ? $makePairs($aDigits, $cDigits) : [];
        $bc = (!empty($bDigits) && !empty($cDigits)) ? $makePairs($bDigits, $cDigits) : [];

        $total = count($ab) + count($ac) + count($bc);

        return [$ab, $ac, $bc, $total];
    }

    /**
     * Enter BC optimized
     */
    public function enterKeyPressOnCrossBc($focus, $value)
    {
        if ($value === 'cross_bc') {
            $this->validate(['cross_bc' => ['required', 'regex:/^[0-9]{2}$/']], [], ['cross_bc' => 'BC']);
            $this->dispatch($focus);
            return;
        }

        if ($value === 'cross_bc_amt') {
            $this->validate([
                'cross_bc' => ['required', 'regex:/^[0-9]{2}$/'],
                'cross_bc_amt' => ['required', 'integer', 'min:1'],
            ], [], ['cross_bc' => 'BC', 'cross_bc_amt' => 'Amount']);

            $payload[] = $this->addCrossOptions(
                bc: $this->cross_bc,
                amt: $this->cross_bc_amt,
                comb: 1,
                number: $this->cross_bc,
                option: 'BC'
            );

            $this->storeCrossAbcIntoCache($payload);

            $this->cross_bc = $this->cross_bc_amt = '';
            $this->AddTicketAndDrawDetailsIntoCrossAbc($this->selected_draw);
            $this->dispatch($focus);
        }
    }

    /**
     * Enter A/B/C optimized (keeps same behavior)
     */
    public function enterKeyPressOnCrossA($focus, $value)
    {
        switch ($value) {
            case 'cross_a':
                $this->dispatch($focus);
                break;
            case 'cross_b':
                $this->dispatch($focus);
                break;
            case 'cross_c':
                $this->dispatch($focus);
                break;
            case 'cross_single_amount':
                [$ab, $ac, $bc, $comb] = $this->generateRegularCombinations();

                $payload[] = $this->addCrossOptions(
                    ab: $ab,
                    ac: $ac,
                    bc: $bc,
                    amt: $this->cross_single_amount,
                    comb: $comb,
                    number: $this->cross_a . '-' . $this->cross_b . '-' . $this->cross_c,
                    option: 'A-B-C'
                );

                $this->storeCrossAbcIntoCache($payload);

                $this->cross_a = $this->cross_b = $this->cross_c = $this->cross_single_amount = '';
                $this->AddTicketAndDrawDetailsIntoCrossAbc($this->selected_draw);
                $this->dispatch($focus);
                break;
        }
    }

    /**
     * Add ticket and draw details into cached cross abc -- optimized to avoid repeated watches
     */
    public function AddTicketAndDrawDetailsIntoCrossAbc(array $selected_draw_ids): void
    {
        $selected_ticket_id = $this->current_ticket_id;

        // get current options once
        $crossOptions = $this->getCrossOptions()->map(function ($crossData) use ($selected_ticket_id, $selected_draw_ids) {
            // Ensure draw ids set, convert to string values once
            $this->selected_draw = array_values(array_unique(array_map('strval', $selected_draw_ids ?? [])));
            $crossData['ticket_id'] = $selected_ticket_id;
            $crossData['draw_details_ids'] = $this->selected_draw;
            return $crossData;
        })->values()->all();

        // Overwrite cache with updated ticket_id/draw ids
        Cache::put('cross_abc', $crossOptions, 7200);

        $this->selected_draw = array_values(array_unique(array_map('strval', $selected_draw_ids ?? [])));
        $this->loadAbcData(true);
        $this->calculateCrossFinalTotal();
    }

    /**
     * Save cross abc entries to DB (keeps existing create() based implementation for events)
     * Consider queueing or batching if this becomes the hot path.
     */
    public function saveCrossAbc()
    {
        // Resolve game IDs once
        $gameIds = [];
        if (is_array($this->selected_games) && count($this->selected_games) > 0) {
            $gameIds = array_values(array_map('intval', $this->selected_games));
        } elseif (!empty($this->game_id)) {
            $gameIds = [(int) $this->game_id];
        } elseif (!empty($this->current_ticket_id)) {
            $gameIds = [
                (int) \App\Models\Ticket::whereKey($this->current_ticket_id)->value('game_id')
            ];
        }

        $cross_data = $this->getCrossOptions();

        // Keep using model create() to preserve events/observers
        foreach ($cross_data as $data) {
            foreach ($gameIds as $gid) {
                $cross_input_data = [
                    'number'           => $data['number'],
                    'combination'      => $data['combination'],
                    'option'           => $data['option'],
                    'draw_details_ids' => array_map('intval', array_values($data['draw_details_ids'] ?? [])),
                    'ticket_id'        => $this->current_ticket_id,
                    'ab'               => $data['ab'],
                    'ac'               => $data['ac'],
                    'bc'               => $data['bc'],
                    'amt'              => $data['amt'],
                    'user_id'          => $this->auth_user->id,
                    'game_id'          => $gid,
                ];

                CrossAbc::create($cross_input_data);
            }
        }
    }

    /**
     * Save cross abc details in bulk (already chunked insert) - left as-is but slightly clarified
     */
    public function saveCrossAbcDetail()
    {
        $now = now('Asia/Kolkata')->format('H:i');

        // Validate draw availability (recompute selected_draw)
        $this->selected_draw = \App\Models\DrawDetail::query()
            ->whereIn('id', $this->selected_draw ?? [])
            ->whereRaw("STR_TO_DATE(end_time, '%H:%i') >= STR_TO_DATE(?, '%H:%i')", [$now])
            ->pluck('id')
            ->all();

        if (empty($this->selected_draw)) {
            $this->addError('selected_draw', 'Selected draw has closed. Please pick an upcoming draw.');
            return;
        }

        // game ids resolution (once)
        $gameIds = [];
        if (is_array($this->selected_games) && count($this->selected_games) > 0) {
            $gameIds = array_values(array_map('intval', $this->selected_games));
        } elseif (!empty($this->game_id)) {
            $gameIds = [(int) $this->game_id];
        } elseif (!empty($this->current_ticket_id)) {
            $gameIds = [
                (int) \App\Models\Ticket::whereKey($this->current_ticket_id)->value('game_id')
            ];
        }

        $cross_data = $this->getCrossOptions();

        // Build AB/AC/BC arrays using flatMap to minimize loops
        $cross_abc = collect(['ab', 'ac', 'bc'])->mapWithKeys(function ($key) use ($cross_data, $gameIds) {
            $items = $cross_data->flatMap(function ($row) use ($key, $gameIds) {
                return collect($row[$key] ?? [])->flatMap(function ($number) use ($row, $gameIds) {
                    return collect($gameIds)->map(function ($gid) use ($row, $number) {
                        return [
                            'number'       => $number,
                            'amount'       => $row['amt'],
                            'combination'  => $row['combination'],
                            'option'       => $row['option'],
                            'ticket_id'    => $this->current_ticket_id,
                            'user_id'      => $this->auth_user->id,
                            'game_id'      => $gid,
                        ];
                    });
                });
            })->values();

            return [$key => $items];
        })->toArray();

        $bulkData = [];

        $ab_data = $cross_abc['ab'];
        $ac_data = $cross_abc['ac'];
        $bc_data = $cross_abc['bc'];

        foreach ($this->selected_draw as $draw_id) {
            foreach ($ab_data as $d) {
                $bulkData[] = array_merge($d, [
                    'draw_detail_id' => $draw_id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                    'type'           => 'AB',
                ]);
            }
            foreach ($ac_data as $d) {
                $bulkData[] = array_merge($d, [
                    'draw_detail_id' => $draw_id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                    'type'           => 'AC',
                ]);
            }
            foreach ($bc_data as $d) {
                $bulkData[] = array_merge($d, [
                    'draw_detail_id' => $draw_id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                    'type'           => 'BC',
                ]);
            }
        }

        // Insert in chunks to avoid large single insert
        collect($bulkData)->chunk(500)->each(function ($chunk) {
            CrossAbcDetail::insert($chunk->toArray());
        });
    }
}
