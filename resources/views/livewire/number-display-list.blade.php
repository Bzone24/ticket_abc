<div class="card mb-3 h-100">

    <!-- Timer Header -->
    @include('livewire.ticket-data-form')

    <div class="card-header text-black">
        <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom w-100">
            @php
                use Illuminate\Support\Str;

                // Decide time format based on presence of seconds
                $formatFromString = function ($s) {
                    return is_string($s) && substr_count($s, ':') >= 2 ? 'g:i:s A' : 'g:i A';
                };

                $addOneMinute = function ($t) use ($formatFromString) {
                    try {
                        $dt = \Illuminate\Support\Carbon::parse($t)->addMinute();
                        $fmt = $formatFromString((string) $t);
                        return $dt->format($fmt);
                    } catch (\Throwable $e) {
                        return $t;
                    }
                };

                if (!empty($selected_times)) {
                    $selected_times_arr = is_array($selected_times) ? $selected_times : [$selected_times];
                    $times = array_map($addOneMinute, $selected_times_arr);
                } elseif (isset($active_draw)) {
                    $raw = $active_draw->formatEndTime();
                    $times = [$addOneMinute($raw)];
                } else {
                    $times = [];
                }

                $labels = !empty($selected_game_labels)
                    ? $selected_game_labels
                    : collect($games ?? [])
                        ->whereIn('id', $selected_games ?? [])
                        ->map(fn($g) => strtoupper($g->code ?? ($g->short_code ?? ($g->name ?? ''))))
                        ->values()
                        ->all();
            @endphp

            <h6 class="mb-0 w-100 text-center">
                @foreach ($this->selectedDraws as $key => $draw)
                    <strong>Draw:</strong> {{ $draw->formatResultTime() }} ,
                    <strong>Game:</strong>{{ $draw->draw->game->name }} |
                @endforeach
            </h6>
        </div>
    </div>

    <div class="card-body pb-0 py-0">
        {{-- Simple ABC Section --}}
        <div class="mb-0">
            <h5 class="fw-semibold">Simple ABC</h5>

            <!-- Fixed height + scroll only inside table area -->
            <div id="printSimpleArea" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-bordered table-striped table-hover mb-0 text-center fw-bold">
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
                            <tr class="text-center fw-bold">
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
                        $total = collect($stored_options)->sum('total');
                        $tq = $total > 0 ? floor($total / 11) : 0;

                        $drawCount = max(1, is_countable($selected_draw) ? count($selected_draw) : 1);

                        if (isset($selected_games) && is_countable($selected_games)) {
                            $gameCount = max(1, count($selected_games));
                        } elseif (!empty($selected_game_labels) && is_countable($selected_game_labels)) {
                            $gameCount = max(1, count($selected_game_labels));
                        } else {
                            $gameCount = 1;
                        }

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
        window.COMBINED_PRINTER = true;

        // helper: clone panel, strip interactive bits and remove last column (Action)
        function cleanPanelHtml(elem, title) {
            if (!elem) return '';
            const table = elem.querySelector('table');
            const wrapper = document.createElement('div');
            wrapper.className = 'panel-wrap';

            if (title) {
                const h = document.createElement('div');
                h.textContent = title;
                h.style.fontWeight = '700';
                h.style.marginBottom = '6px';
                wrapper.appendChild(h);
            }

            if (table) {
                const t = table.cloneNode(true);

                // Remove interactive elements and attributes in one pass
                t.querySelectorAll('button, input, textarea, select, .no-print, .btn').forEach(n => n.remove());
                t.querySelectorAll('[wire\\:click]').forEach(n => n.removeAttribute('wire:click'));
                t.querySelectorAll('[wire\\:key]').forEach(n => n.removeAttribute('wire:key'));

                // Remove Action column from header and body only
                t.querySelectorAll('thead tr').forEach(r => {
                    const lastTh = r.querySelector('th:last-child');
                    if (lastTh) lastTh.remove();
                });
                t.querySelectorAll('tbody tr').forEach(r => {
                    const lastTd = r.querySelector('td:last-child');
                    if (lastTd) lastTd.remove();
                });

                // Remove buttons inside tfoot but keep footer totals structure
                t.querySelectorAll('tfoot button, tfoot .btn, tfoot input, tfoot select, tfoot textarea').forEach(n => n
                    .remove());

                wrapper.appendChild(t);
            } else {
                const clone = elem.cloneNode(true);
                clone.querySelectorAll('button, input, textarea, select, .no-print, .btn').forEach(n => n.remove());
                clone.querySelectorAll('[wire\\:click]').forEach(n => n.removeAttribute('wire:click'));
                wrapper.appendChild(clone);
            }

            return wrapper.outerHTML;
        }

        function extractVisibleTicketAndDraw() {
            const text = (document.body && document.body.innerText) ? document.body.innerText : '';
            let ticketNo = '';
            let drawText = '';

            const tn = text.match(/Ticket\s*No[:\s]*([A-Za-z0-9\-\_]+)/i);
            if (tn) ticketNo = tn[1].trim();

            const d = text.match(/Draw[:\s]*([^\n\r]+)/i);
            if (d) {
                drawText = d[1].trim().replace(/\s{2,}/g, ' ');
                drawText = drawText.split(/\r?\n/)[0].trim();
            }

            return {
                ticketNo,
                drawText
            };
        }

        /**
         * printStackedTicket
         * - Builds the same HTML payload as before
         * - Creates a hidden iframe, writes the HTML into it and calls print()
         * - Removes the iframe after printing
         */
        function printStackedTicket() {
            setTimeout(() => {
                const simpleElem = document.getElementById('printSimpleArea');
                const crossElem = document.getElementById('printCrossArea');

                const classAreas = Array.from(document.querySelectorAll('.print-area'));
                const simple = simpleElem || classAreas[0] || null;
                const cross = crossElem || classAreas[1] || classAreas[0] || null;

                if (!simple && !cross) {
                    alert('No ticket content found to print (Simple / Cross panels missing).');
                    return;
                }

                const simpleHtml = simple ? cleanPanelHtml(simple, 'Simple ABC') : '';
                const crossHtml = cross ? cleanPanelHtml(cross, 'Cross ABC') : '';

                // prefer server-provided values if present, otherwise read from page
                let ticketNo = {!! json_encode($selected_ticket->ticket_number ?? null) !!} || '';
                let drawTimeRaw = {!! json_encode($times ?? null) !!} || null;

                let drawTime = '';
                if (Array.isArray(drawTimeRaw)) {
                    drawTime = drawTimeRaw.join(', ');
                } else if (typeof drawTimeRaw === 'string' && drawTimeRaw.length) {
                    drawTime = drawTimeRaw;
                }

                // fallback to visible page if either missing
                if (!ticketNo || !drawTime) {
                    const visible = extractVisibleTicketAndDraw();
                    if (!ticketNo && visible.ticketNo) ticketNo = visible.ticketNo;
                    if (!drawTime && visible.drawText) drawTime = visible.drawText;
                }

                ticketNo = ticketNo || '';
                drawTime = drawTime || '';

                const styles = `
                <style>
                  body { font-family: monospace; font-size: 12px; margin: 0; padding: 4px; color:#000; }
                  table { width: 100%; border-collapse: collapse; }
                  th, td { padding: 4px 6px; border-bottom: 1px dotted #000; text-align: left; font-size:11px; }
                  thead th { font-weight:700; }
                  .section { margin-bottom: 10px; }
                  .title { font-weight:bold; text-align:center; margin: 6px 0; }
                  .panel-wrap { margin-bottom:8px; }
                </style>
            `;

                const html = `
                <html>
                  <head><title>Print Ticket</title>${styles}</head>
                  <body>
                    <div class="title">Ticket No: ${ticketNo} | Draw: ${drawTime}</div>
                    <div class="section">${simpleHtml}</div>
                    <div class="section">${crossHtml}</div>
                    <div style="margin-top:8px; font-size:10px;">Printed: ${ new Date().toLocaleString() }</div>
                  </body>
                </html>
            `;

                // Create a hidden iframe and write the HTML into it
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                iframe.style.overflow = 'hidden';
                iframe.setAttribute('aria-hidden', 'true');

                // Append first so srcdoc / onload fire reliably in browsers
                document.body.appendChild(iframe);

                // Use srcdoc if supported, otherwise write into iframe document
                try {
                    if ('srcdoc' in iframe) {
                        iframe.srcdoc = html;
                        iframe.onload = function() {
                            try {
                                iframe.contentWindow.focus();
                                iframe.contentWindow.print();
                            } catch (err) {
                                // fallback to writing and printing
                                try {
                                    iframe.contentDocument.open();
                                    iframe.contentDocument.write(html);
                                    iframe.contentDocument.close();
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                } catch (e) {
                                    // last resort: alert user
                                    alert('Printing failed in this browser.');
                                }
                            }
                            // remove iframe after small delay to allow print dialog to initialize
                            setTimeout(() => iframe.remove(), 1000);
                        };
                    } else {
                        // Older browsers: write directly
                        const idoc = iframe.contentWindow.document;
                        idoc.open();
                        idoc.write(html);
                        idoc.close();
                        iframe.onload = function() {
                            try {
                                iframe.contentWindow.focus();
                                iframe.contentWindow.print();
                            } catch (err) {
                                alert('Printing failed in this browser.');
                            }
                            setTimeout(() => iframe.remove(), 1000);
                        };
                    }
                } catch (e) {
                    // If iframe approach fails, fallback to original inline print (may open new tab)
                    const w = window.open('', '', 'width=380,height=800');
                    if (!w) {
                        alert('Popup blocked — allow popups to print.');
                        return;
                    }
                    w.document.open();
                    w.document.write(html);
                    w.document.close();
                    w.focus();
                    setTimeout(() => {
                        w.print();
                        w.close();
                    }, 200);
                }
            }, 150);
        }

        // Trigger print after server-side emit (keeps original behavior)
        Livewire.on('ticketSubmitted', () => {
            printStackedTicket();
        });

        // Also call print on Submit button click immediately (non-blocking)
        document.addEventListener('click', function(e) {
            const el = e.target.closest && e.target.closest('.btn[wire\\:click="submitTicket"]');
            if (el) {
                // small delay so Livewire can start processing if needed
                setTimeout(printStackedTicket, 80);
            }
        }, true);

        // Keyboard shortcut: F12 triggers printStackedTicket (does not block default F12)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12') {
                printStackedTicket();
            }
        });
    </script>
@endscript
