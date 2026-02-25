<?php
namespace DafCore\Components\Routing;

use DafCore\Component;
use DafCore\IViewManager;
use DafCore\Views\Components\SystemComponent;

/**
 * @daf-summary Renders the `<PageView>` Component in the currently selected layout.
 *
 * @daf-note PageCallback auto set from cascades value by the `<RouteView>` Component.
 */
final class RouteView extends Component
{
    // Callback that returns the resolved page output
    public \Closure $PageCallback;
    private IViewManager $viewManager;

    public function OnLoad(): void
    {
        $this->viewManager = $this->Inject(IViewManager::class);
    }

    public function Render(): string
    {
        $layout = $this->viewManager->GetLayout();
        $layoutSc = new SystemComponent($layout);
        $layoutSc->Cascades['PageCallback'] = ['for' => PageView::class, 'value' => $this->PageCallback];
        return $layoutSc->Render();
    }

}
