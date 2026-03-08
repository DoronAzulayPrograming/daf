<?php
namespace DafCore\Components\Routing;

use Closure;
use DafCore\Component;

/**
 * @daf-summary Executes the cascaded `PageCallback` and returns its output.
 */
final class PageView extends Component
{
    // Callback that returns final rendered content
    public Closure $PageCallback;

    public function Render(): string
    {
        $func = $this->PageCallback;
        return $func();
    }
}
