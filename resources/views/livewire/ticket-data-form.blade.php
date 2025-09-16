<div class="card shadow-lg border-0 rounded-3 bg-dark text-light">
    <div wire:poll.10s="syncDuration"></div>

    <!-- Header -->
    <div class="card-header bg-primary bg-gradient text-white rounded-top">
        <div class="d-flex justify-content-between align-items-center w-100">

            <!-- Left side: Draw & Ticket Info -->
            <div class="d-flex flex-column">
                <h5 class="fw-bold mb-0">Ticket No: {{ $selected_ticket_number }}</h5>
            </div>

            <!-- Right side: Countdown -->
     <div class="fw-bold" 
     x-data="{
        timeLeft: @entangle('duration'),
        interval: null,
        syncInterval: null,
        startCountdown() {
            if (this.interval) clearInterval(this.interval);
            this.interval = setInterval(() => {
                if (this.timeLeft > 0) {
                    this.timeLeft--;
                    this.$dispatch('countdown-tick', { timeLeft: this.timeLeft });
                } else {
                    clearInterval(this.interval); // ✅ fixed
                    Livewire.dispatch('refresh-draw'); // ✅ safe refresh
                }
            }, 1000);

            // 🔄 Resync every 15s with backend value
            if (this.syncInterval) clearInterval(this.syncInterval);
            this.syncInterval = setInterval(() => {
                Livewire.dispatch('sync-duration');
            }, 15000);
        }
     }" 
     x-init="startCountdown()" 
     x-effect="if (timeLeft >= 0) startCountdown()"> <!-- ✅ prevent multiple intervals -->


 


                <span class="badge bg-danger text-white px-3 py-2" style="font-size: larger;">
                    ⏳
                    <span x-text="
                        'Time Left: ' +
                        String(Math.floor((timeLeft % 3600) / 60)).padStart(2, '0') + ':' +
                        String(timeLeft % 60).padStart(2, '0')
                    "></span>
                </span>
            </div>
        </div>
    </div>

    <!-- Body -->
</div>
