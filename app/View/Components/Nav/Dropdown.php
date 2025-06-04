<?php
namespace App\View\Components\Nav;

use Illuminate\View\Component;

class Dropdown extends Component
{
    public string $label;
    public array $routes;
    public string $active;

    public function __construct(string $label, array $routes, string $active)
    {
        $this->label = $label;
        $this->routes = $routes;
        $this->active = $active;
    }

    public function render()
    {
        return view('components.nav.dropdown');
    }
}

