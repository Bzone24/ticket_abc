<div class="card shadow-lg border-0 rounded-3 bg-dark text-light h-100">
    <div class="card-header bg-warning bg-gradient text-dark rounded-top">
        <h5 class="mb-0">
            <i class="bi bi-calendar-week me-2"></i> Draw List
        </h5>
    </div>

    <div class="card-body p-0 d-flex flex-column" wire:poll.5s="loadDraws">
        <!-- Scrollable list -->
        <div class="list-group list-group-flush overflow-auto" style="height:400px; font-size: larger;">

            <!-- TOP: Game checkboxes (N1, N2) -->
            <div class="list-group-item border-0 py-2 bg-dark text-light position-sticky top-0 z-1"
                 wire:key="game-select-header"
                 style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                {{-- <div class="d-flex align-items-center">
                    <i class="bi bi-controller me-2"></i>
                    <span class="fw-semibold me-2">Select Game:</span>

                    @php
                        $topGames = collect($games ?? [])
                            ->filter(function ($g) {
                                $code = $g->code ?? $g->short_code ?? $g->name ?? '';
                                return in_array(strtoupper($code), ['N1','N2']);
                            })
                            ->sortBy(function ($g) {
                                $code = strtoupper($g->code ?? $g->short_code ?? $g->name ?? '');
                                return $code === 'N1' ? 0 : 1; // N1 first, then N2
                            });
                    @endphp

                    @foreach ($topGames as $g)
                        @php $label = strtoupper($g->code ?? $g->short_code ?? $g->name); @endphp
                        <div class="form-check form-check-inline ms-3" wire:key="game-pill-{{ $g->id }}">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="game_{{ $g->id }}"
                                value="{{ $g->id }}"
                                wire:model="selected_games"
                            >
                            <label class="form-check-label" for="game_{{ $g->id }}">
                                <span class="badge bg-secondary">{{ $label }}</span>
                            </label>
                        </div>
                    @endforeach
                </div> --}}
            </div>
            <!-- /TOP: Game checkboxes -->
            @forelse ($draw_list as $draw_detail)
                <div class="list-group-item border-0 py-2 d-flex align-items-center
                            {{ $active_draw && $active_draw->id === $draw_detail->id ? 'active bg-danger text-white' : 'bg-dark text-light' }}"
                     wire:key="draw-{{ $draw_detail->id }}">

                    <!-- Checkbox -->
                    <input
                        class="form-check-input me-2 draw_checkbox"
                        type="checkbox"
                        id="draw_{{ $draw_detail->id }}"
                        value="{{ $draw_detail->id }}"
                        wire:model="selected_draw"
                    >

                    <!-- Label -->
                    <label class="form-check-label flex-grow-1" for="draw_{{ $draw_detail->id }}">
                        <span class="fw-semibold">
                            {{ $draw_detail->formatResultTime() }} Game : {{$draw_detail->draw->game->name}}
                        </span>
                        <span class="ms-2 text-warning fw-bold">
                            Cross Amt:
                            {{ $draw_detail->totalAbAmt(auth()->id()) + $draw_detail->totalBcAmt(auth()->id()) + $draw_detail->totalAcAmt(auth()->id()) }}
                        </span>
                        <span class="ms-2 text-white fw-bold">
                            TQ:
                            {{ $draw_detail->totalAqty(user_id: auth()->id()) + $draw_detail->totalCqty(user_id: auth()->id()) }}
                        </span>
                    </label>
                </div>
            @empty
                <div class="text-center text-muted py-3">No draws available.</div>
            @endforelse
        </div>
    </div>
</div>
