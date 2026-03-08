<?php
namespace DafCore\Components\Routing;

use DafCore\Component;

/**
 * @daf-summary Focuses the first element matching `Selector` after navigation for accessibility.
 */
class FocusOnNavigate extends Component {

    // CSS selector used to find the target element to focus
    public string $Selector;

    public function OnLoad(): void {
        $outlet = $this->Inject(\DafCore\Views\ScriptsOutlet::class);
        
        $outlet->AddContent('FocusOnNavigate', "<script>
            function focusFirstBySelector(selector) {
                const element = document.querySelector(selector);
                if (!element || typeof element.focus !== 'function') return null;
                if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '-1');
                element.focus({ preventScroll: true });
                return element;
            }
            focusFirstBySelector('{$this->Selector}');
        </script>");
    }

    public function Render(): string { return ""; }
}