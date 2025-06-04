<?php

namespace App\Livewire;

use Livewire\Component;

class TestEvent extends Component
{
    public function fire()
    {
        $this->dispatchBrowserEvent('hello'); // <=== 就測這句
    }

    public function render()
    {
        return view('livewire.test-event');
    }
}

