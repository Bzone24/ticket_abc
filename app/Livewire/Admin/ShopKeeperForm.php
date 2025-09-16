<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Traits\TicketNumber;
use Livewire\Component;

class ShopKeeperForm extends Component
{
    use TicketNumber;

    public $first_name;

    public $last_name;

    public $email;

    public $password;

    public $password_confirmation;

    public $mobile_number;

    public $showPassword = false;

    public $showConfirmPassword = false;

    public $maximum_cross_amount = 0;

    public $maximum_tq = 0;

    public $existingUser = null;

    // public $userId;

    public $rules = [
        'first_name' => 'required|string|min:3|max:25',
        'last_name' => 'required|string|min:3|max:25',
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'mobile_number' => ['required', 'string', 'min:10', 'max:12', 'unique:users,mobile_number'],
        'password' => ['required', 'string', 'min:6', 'max:20', 'confirmed'],
        'maximum_cross_amount'=> ['required', 'numeric' ],
        'maximum_tq'=> ['required', 'numeric' ],
    ];

    public function getUser($user_id)
    {
        return User::where('id', $user_id)->first();
    }

    public function mount($user = null)
    {
        if ($user) {
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->email = $user->email;
            $this->mobile_number = $user->mobile_number;
            $this->maximum_cross_amount = $user->maximum_cross_amount;
            $this->maximum_tq = $user->maximum_tq;
            $this->rules['email'] = [
                'required',
                'string',
                'email', 'unique:users,email,'.$user->id];
            $this->rules['mobile_number'] = ['required', 'string', 'min:10', 'max:12', 'unique:users,mobile_number,'.$user->id];
            $this->existingUser = $user;
        }
    }

    public function togglePasswordVisibility($field)
    {
        if ($field === 'password') {
            $this->showPassword = ! $this->showPassword;
        } elseif ($field === 'confirm') {
            $this->showConfirmPassword = ! $this->showConfirmPassword;
        }
    }

    public function save()
    {
        $shop_keeper_input_data = $this->validate($this->rules);
        $shop_keeper_input_data['password_plain'] = $this->password;

        if ($this->existingUser) {
            $this->existingUser->update($shop_keeper_input_data);
        } else {
            $user = User::create($shop_keeper_input_data);
            $user['ticket_series'] = $this->generateTicketNumberFromId($user->id);
            $user->save();
        }

        return redirect()->route('admin.shopkeepers');

    }

    public function render()
    {
        return view('livewire.admin.shop-keeper-form');
    }
}
