<div class="card mb-3 h-100">

    <!-- Timer Header -->
    @include('livewire.ticket-data-form')

    <div class="card-header text-black">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom w-100">

            @php
                // TIMES: prefer $selected_times; fallback to active_draw->formatEndTime()
                $times = !empty($selected_times)
                    ? $selected_times
                    : (isset($active_draw)
                        ? [$active_draw->formatEndTime()]
                        : []);

                // GAMES: prefer $selected_game_labels; fallback from games list
                $labels = !empty($selected_game_labels)
                    ? $selected_game_labels
                    : collect($games ?? [])
                        ->whereIn('id', $selected_games ?? [])
                        ->map(fn($g) => strtoupper($g->code ?? ($g->short_code ?? ($g->name ?? ''))))
                        ->values()
                        ->all();
            @endphp

            <h6 class="mb-0 w-100 text-center">
                <strong>Game:</strong> {{ !empty($labels) ? implode(', ', $labels) : '—' }}
                &nbsp; | &nbsp;
                <strong>Draw:</strong> {{ !empty($times) ? implode(', ', $times) : '—' }}
            </h6>

        </div>
    </div>

    <div class="card-body pb-2">
        {{-- Simple ABC Section --}}
        <div class="mb-2">
            <h5 class="fw-semibold">Simple ABC</h5>

            <!-- ✅ Fixed height + scroll only inside table area -->
            <div id="printSimpleArea" style="max-height: 300px; overflow-y: auto;">

                <table class="table table-bordered table-striped table-hover mb-0">
                    <thead class="table-light position-sticky top-0" style="z-index: 1;">
                        <tr>
                            <th>#</th>
                            <th>Option</th>
                            <th>Number</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse (collect($stored_options)->sortBy('option') as $index => $option)
                            <tr>
                                <td>{{ $loop->index + 1 }}</td>
                                <td>{{ $option['option'] }}</td>
                                <td>{{ $option['number'] }}</td>
                                <td>{{ $option['qty'] }}</td>
                                <td>{{ $option['total'] }}</td>
                                <td>
                                    <button class="btn btn-sm btn-danger" wire:click="deleteOption({{ $index }})"
                                        title="Delete">
                                        🗑
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>

                    @php
                        // base total and TQ as before
                        $total = collect($stored_options)->sum('total');
                        $tq = $total > 0 ? floor($total / 11) : 0; // divide total by 11

                        // number of selected draws (existing variable in your code)
                        $drawCount = max(1, is_countable($selected_draw) ? count($selected_draw) : 1);

                        // number of selected games (use $selected_games if that's the Livewire prop;
                        // fall back to $selected_game_labels or 1 if not present)
                        if (isset($selected_games) && is_countable($selected_games)) {
                            $gameCount = max(1, count($selected_games));
                        } elseif (!empty($selected_game_labels) && is_countable($selected_game_labels)) {
                            $gameCount = max(1, count($selected_game_labels));
                        } else {
                            $gameCount = 1;
                        }

                        // final total multiplies base total by draws AND games
                        $finalTotal = $total * $drawCount * $gameCount;
                    @endphp

                    <tfoot class="table-light position-sticky bottom-0" style="z-index: 2; background: #fff;">
                        <tr>
                            <td colspan="4" class="fw-bold text-danger ">
                                <div class="d-flex flex-column ">
                                    <span>
                                        TQ: {{ $tq }}
                                    </span>
                                    <span>
                                        Total: {{ $total }}
                                    </span>
                                    <span>
                                        Final Total (× {{ $drawCount }} draws × {{ $gameCount }} games):
                                        {{ $finalTotal }}
                                    </span>
                                    <span>
                                        @error('draw_detail_simple')
                                            <div class="text-red-500">{{ $message }}</div>
                                            {{-- <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                {{ $message }}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div> --}}
                                        @enderror
                                    <span>
                                </div>
                            </td>
                            <td colspan="2" class="text-end">
                                <div class="d-flex flex-column gap-2">
                                    <button class="btn btn-sm btn-danger" wire:click="clearAllOptionsIntoCache()">
                                        Clear All
                                    </button>
                                    <button class="btn btn-sm btn-primary" wire:click="submitTicket">
                                        Submit Ticket
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tfoot>

                </table>
            </div>
        </div>
    </div>

</div>


{{-- Print + Shortcut Script --}}
@script
    <script>
        document.addEventListener('keydown', function(e) {
            // Detect Ctrl + F12
            if (e.ctrlKey && e.key === "F12") {
                e.preventDefault();
                @this.call('submitTicket');
            }
        });

        // After save -> print simple ticket
        Livewire.on('ticketSubmitted', () => {
            printSimpleTicket();
        });

        function printSimpleTicket() {
            let content = document.getElementById("printSimpleArea")?.innerHTML ?? '';
            let printWindow = window.open("", "", "width=300,height=600");
            printWindow.document.write(`
            <html>
                <head>
                    <title>Print Simple Ticket</title>
                        <style>
                            body { font-family: monospace; font-size: 12px; margin: 0; padding: 2px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 2px 0; }
                            th { font-weight: bold; }
                            hr { border: none; border-top: 1px dashed #000; margin: 4px 0; }
                        </style>
                </head>
                <body>
                    <div style="text-align:center; margin-bottom:4px;">
                        <strong>Ticket No:</strong> {{ $selected_ticket->ticket_number ?? '' }}<br>
                        <strong>Time:</strong> {{ is_array($selected_times) ? implode(', ', $selected_times) : $selected_times }}
                    </div>
                    <hr>
                        ${content}
                    </body>
            </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
@endscript
