<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PlotInfoForm extends Component
{
    /**
     * Create a new component instance.
     */

    public $form;

    public function __construct($form = [])
    {
        $this->form = $form;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.plot-info-form');
    }
}
