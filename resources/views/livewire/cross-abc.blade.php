<div class="card shadow-lg border-0 rounded-3 bg-dark text-light">
    <!-- Header -->
    <div class="card-header bg-danger bg-gradient text-white rounded-top">
        <h3 class="text-center mb-0">
            <i class="bi bi-shuffle me-2"></i> Cross ABC
        </h3>
    </div>

    <!-- Body -->
    <div class="card-body" style="background-color:#212120;">
        
        <!-- ABC + Amt + Comb -->
        <div class="row mb-3 p-2 rounded" style="background:#df4c5a;" 
             x-data 
             @focus-cross-abc.window="document.getElementById('cross_abc').focus()"
             @focus-cross-abc-qty.window="document.getElementById('cross_qty').focus()"
             @focus-cross-abc-combination.window="document.getElementById('cross_combination').focus()">

            <div class="col-4">
                <label class="form-label text-dark fw-bold" for="cross_abc">ABC</label>
                <input type="text" id="cross_abc"
                       wire:model="cross_abc_input"
                       wire:keydown.enter="enterKeyPressOnCrossAbc('focus-cross-abc-qty','cross_abc_input')"
                       class="form-control bg-light text-dark border-warning cross-input cross_number"
                       placeholder="Enter ABC">
                @error('cross_abc_input') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>

            <div class="col-4">
                <label class="form-label text-dark fw-bold" for="cross_qty">Amt</label>
                <input type="text" id="cross_qty"
                       wire:model="cross_abc_amt"
                       wire:keydown.enter="enterKeyPressOnCrossAbc('focus-cross-abc-combination','cross_abc_amt')"
                       class="form-control bg-light text-dark border-warning cross-input"
                       placeholder="Enter Amt">
                @error('cross_abc_amt') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>

            <div class="col-4">
                <label class="form-label text-dark fw-bold" for="cross_combination">Comb</label>
                <input type="text" id="cross_combination"
                       wire:model="cross_combination"
                       wire:keydown.enter="enterKeyPressOnCrossAbc('focus-cross-a','cross_combination')"
                       class="form-control bg-light text-dark border-warning cross-input"
                       placeholder="Enter Combination">
                @error('cross_combination') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Single A + B + C -->
        <div class="row mb-3 p-2 rounded" style="background:#e1e7e0;"
             x-data 
             @focus-cross-a.window="document.getElementById('cross_a').focus()"
             @focus-cross-b.window="document.getElementById('cross_b').focus()"
             @focus-cross-c.window="document.getElementById('cross_c').focus()"
             @focus-cross-single-amt.window="document.getElementById('cross_single_amount').focus()">

            <div class="col-12 d-flex gap-2">
                <div>
                    <label class="form-label text-dark fw-bold" for="cross_a">A</label>
                    <input type="text" id="cross_a"
                           wire:model="cross_a"
                           wire:keydown.enter="enterKeyPressOnCrossA('focus-cross-b','cross_a')"
                           class="form-control bg-light text-dark border-danger text-center cross-input">
                    @error('cross_a') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="form-label text-dark fw-bold" for="cross_b">B</label>
                    <input type="text" id="cross_b"
                           wire:model="cross_b"
                           wire:keydown.enter="enterKeyPressOnCrossA('focus-cross-c','cross_b')"
                           class="form-control bg-light text-dark border-danger text-center cross-input">
                    @error('cross_b') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="form-label text-dark fw-bold" for="cross_c">C</label>
                    <input type="text" id="cross_c"
                           wire:model="cross_c"
                           wire:keydown.enter="enterKeyPressOnCrossA('focus-cross-single-amt','cross_c')"
                           class="form-control bg-light text-dark border-danger text-center cross-input">
                    @error('cross_c') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="form-label text-dark fw-bold" for="cross_single_amount">Amt</label>
                    <input type="text" id="cross_single_amount"
                           wire:model="cross_single_amount"
                           wire:keydown.enter="enterKeyPressOnCrossA('focus-cross-ab','cross_single_amount')"
                           class="form-control bg-light text-dark border-danger text-center cross-input">
                    @error('cross_single_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- AB -->
        <div class="row mb-3 p-2 rounded" style="background:#0dcaf0;" 
             x-data 
             @focus-cross-ab.window="document.getElementById('cross_ab').focus()"
             @focus-cross-ab-amt.window="document.getElementById('cross_ab_amt').focus()">

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_ab">AB</label>
                <input type="text" id="cross_ab"
                       wire:model="cross_ab"
                       wire:keydown.enter="enterKeyPressOnCrossAb('focus-cross-ab-amt','cross_ab')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter AB">
                @error('cross_ab') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_ab_amt">Amt</label>
                <input type="text" id="cross_ab_amt"
                       wire:model="cross_ab_amt"
                       wire:keydown.enter="enterKeyPressOnCrossAb('focus-cross-bc','cross_ab_amt')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter Amt">
                @error('cross_ab_amt') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- BC -->
        <div class="row mb-3 p-2 rounded" style="background:#0dcaf0;" 
             x-data 
             @focus-cross-bc.window="document.getElementById('cross_bc').focus()"
             @focus-cross-bc-amt.window="document.getElementById('cross_bc_amt').focus()">

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_bc">BC</label>
                <input type="text" id="cross_bc"
                       wire:model="cross_bc"
                       wire:keydown.enter="enterKeyPressOnCrossBc('focus-cross-bc-amt','cross_bc')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter BC">
                @error('cross_bc') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_bc_amt">Amt</label>
                <input type="text" id="cross_bc_amt"
                       wire:model="cross_bc_amt"
                       wire:keydown.enter="enterKeyPressOnCrossBc('focus-cross-ac','cross_bc_amt')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter Amt">
                @error('cross_bc_amt') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- AC -->
        <div class="row mb-3 p-2 rounded" style="background:#0dcaf0;" 
             x-data 
             @focus-cross-ac.window="document.getElementById('cross_ac').focus()"
             @focus-cross-ac-amt.window="document.getElementById('cross_ac_amt').focus()">

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_ac">AC</label>
                <input type="text" id="cross_ac"
                       wire:model="cross_ac"
                       wire:keydown.enter="enterKeyPressOnCrossAc('focus-cross-ac-amt','cross_ac')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter AC">
                @error('cross_ac') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>

            <div class="col-6">
                <label class="form-label text-dark fw-bold" for="cross_ac_amt">Amt</label>
                <input type="text" id="cross_ac_amt"
                       wire:model="cross_ac_amt"
                       wire:keydown.enter="enterKeyPressOnCrossAc('focus-cross-ac','cross_ac_amt')"
                       class="form-control bg-light text-dark border-dark cross-input"
                       placeholder="Enter Amt">
                @error('cross_ac_amt') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>

@script
<script>
    // === Input restrictions ===
    $(document).on("input", ".cross_number", function() {
        let val = $(this).val().replace(/[^0-9]/g, ''); 
        val = [...new Set(val.split(''))].join('').substring(0, 3);
        $(this).val(val);
    });

    $(document).on("input", ".mynumber", function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // === Shortcuts ===
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'c') {
            e.preventDefault(); document.getElementById('cross_abc')?.focus();
        }
        if (e.ctrlKey && ['1','2','3'].includes(e.key)) {
            e.preventDefault(); 
            document.getElementById({
                '1':'cross_a','2':'cross_b','3':'cross_c'
            }[e.key])?.focus();
        }
    });
</script>
@endscript

<style>
  /* One rule for all Cross ABC inputs */
  .cross-input {
    font-size: 1.1rem !important;
    font-weight: bold !important;
    text-align: center !important;
    letter-spacing: 2px;
  }

  /* Focus state */
  .cross-input:focus {
    border: 2px solid #00ff88 !important;
    background-color: #2f2f2f !important;
    color: #ffffff !important;
  }
</style>
