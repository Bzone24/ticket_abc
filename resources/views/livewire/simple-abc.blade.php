<div class="card shadow-lg border-0 rounded-3 bg-dark text-light">
    <!-- Header -->
    <div class="card-header bg-success bg-gradient text-white rounded-top">
        <h3 class="text-center mb-0">
            <i class="bi bi-123 me-2"></i> Simple ABC
        </h3>
    </div>

    <!-- Body -->
    <div class="card-body" style="background:#212120;">
        
        {{-- ✅ Ticket Info --}}
        @if ($selected_ticket)
            <div class="text-center mb-3 p-2 rounded" style="background:#333; color:#fff;">
                <strong>Ticket No:</strong> {{ $selected_ticket->ticket_number }} <br>
                <strong>Time:</strong> {{ is_array($selected_times) ? implode(', ', $selected_times) : $selected_times }}
            </div>
        @endif

        {{-- 🔍 Debug --}}
        {{-- <div style="color:yellow;">
            DEBUG: Ticket = {{ $selected_ticket ? $selected_ticket->ticket_number : 'none' }} <br>
            Times = {{ is_array($selected_times) ? implode(', ', $selected_times) : $selected_times }}
        </div> --}}

        <div class="row mb-3 p-2 rounded" style="background:#2c9162;"  {{-- Purple background for ABC --}}
             x-data 
             @focus-qty.window="document.getElementById('qty').focus()"
             @focus-abc.window="document.getElementById('abc').focus()">
             
            <!-- ABC Input -->
            <div class="col-6">
                <label class="form-label text-white fw-bold" for="abc">ABC</label>
                <input type="text" 
                       class="form-control bg-light text-dark border-dark" 
                       wire:model="abc" id="abc"
                       wire:keydown.enter="enterKeyPressOnAbc" 
                       placeholder="Enter ABC">
                @error('abc')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </div>

            <!-- Qty Input -->
            <div class="col-6">
                <label class="form-label text-white fw-bold" for="qty">Qty</label>
                <input type="text" 
                       class="form-control bg-light text-dark border-dark" 
                       wire:model="abc_qty" id="qty"
                       wire:keydown.enter="enterKeyPressOnQty" 
                       placeholder="Enter Qty">
                @error('abc_qty')
                    <span class="text-danger small">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle mb-0 text-center">
                <thead class="bg-success text-white">
                    <tr>
                        <th>Option</th>
                        <th>#Num (0–9)</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody 
                    x-data 
                    @focus-b.window="document.getElementById('input_b').focus()"
                    @focus-a.window="document.getElementById('input_a').focus()"
                    @focus-c.window="document.getElementById('input_c').focus()"
                    @focus-a_qty.window="document.getElementById('input_a_qty').focus()"
                    @focus-b_qty.window="document.getElementById('input_b_qty').focus()"
                    @focus-c_qty.window="document.getElementById('input_c_qty').focus()">
                    
                    <!-- A Row -->
                    <tr>
                        <td class="bg-success text-white text-center" ><b>A</b></td>
                        <td>
                            <input type="text" id="input_a" 
                                   wire:model.debounce.250ms='a'
                                   wire:keydown.down="move('focus-b','a')" 
                                   wire:keydown.right="move('focus-a_qty','a')" 
                                   wire:keydown.tab="keyTab('a')" 
                                   class="form-control bg-light text-dark border-warning text-center zeroToNineNumber">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control bg-light text-dark border-warning text-center number_qty" 
                                   id="input_a_qty" wire:model="a_qty"
                                   wire:keydown.left="move('focus-a','a')" 
                                   wire:keydown.down="move('focus-b_qty','a')" 
                                   wire:keydown.tab="keyTab('a')"
                                   wire:keydown.enter="keyEnter('a','focus-a')">
                        </td>
                    </tr>

                    <!-- B Row -->
                    <tr>
                        <td class="bg-warning text-dark text-center"><b>B</b></td>
                        <td>
                            <input type="text" id="input_b"
                                   wire:model.debounce.250ms='b'
                                   wire:keydown.up="move('focus-a','b')" 
                                   wire:keydown.down="move('focus-c','b')"
                                   wire:keydown.right="move('focus-b_qty','b')" 
                                   wire:keydown.tab="keyTab('b')" 
                                   class="form-control bg-light text-dark border-warning text-center zeroToNineNumber">
                        </td>
                        <td>
                            <input type="text" class="form-control bg-light text-dark border-warning text-center number_qty"
                                   id="input_b_qty" wire:model="b_qty"
                                   wire:keydown.left="move('focus-b','b')" 
                                   wire:keydown.down="move('focus-c_qty','b')"
                                   wire:keydown.tab="keyTab('b')" 
                                   wire:keydown.up="move('focus-a_qty','b')" 
                                   wire:keydown.enter="keyEnter('b','focus-b')">
                        </td>
                    </tr>

                    <!-- C Row -->
                    <tr>
                        <td class="bg-info text-white text-center"><b>C</b></td>
                        <td>
                            <input type="text" id="input_c"
                                   wire:model.debounce.250ms='c'
                                   wire:keydown.up="move('focus-b','c')" 
                                   wire:keydown.right="move('focus-c_qty','c')"
                                   wire:keydown.tab="keyTab('c')" 
                                   class="form-control bg-light text-dark border-warning text-center zeroToNineNumber">
                        </td>
                        <td>
                            <input type="text" class="form-control bg-light text-dark border-warning text-center number_qty"
                                   id="input_c_qty" wire:model="c_qty"
                                   wire:keydown.left="move('focus-c','c')" 
                                   wire:keydown.up="move('focus-b_qty','c')"
                                   wire:keydown.tab="keyTab('c')" 
                                   wire:keydown.enter="keyEnter('c','focus-c')">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('keydown', function (e) {
        // Ctrl + A → focus input_a
        if (e.ctrlKey && !e.shiftKey && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            let el = document.getElementById('input_a');
            if (el) el.focus();
        }

        // Ctrl + B → focus input_b
        if (e.ctrlKey && e.key.toLowerCase() === 'b') {
            e.preventDefault();
            let el = document.getElementById('input_b');
            if (el) el.focus();
        }

        // Ctrl + C → focus input_c
        if (e.ctrlKey && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            let el = document.getElementById('input_c');
            if (el) el.focus();
        }

        // Ctrl + Shift + A → focus ABC main input
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            let el = document.getElementById('abc');
            if (el) el.focus();
        }
    });
</script>
@endscript

@script
<style>
  /* Highlight ONLY the fields you care about */
  #input_a:focus,
  #input_b:focus,
  #input_c:focus,
  #abc:focus {
    outline: none !important;
    border: 2px solid #00ff88 !important;
    box-shadow:
      0 0 0 .25rem rgba(0,255,136,.25),
      0 0 10px rgba(0,255,136,.7) !important;
    background-color: #2a2a2a !important;
    color: #ffffff !important; /* ensure readable text on dark bg */
  }
</style>
@endscript
