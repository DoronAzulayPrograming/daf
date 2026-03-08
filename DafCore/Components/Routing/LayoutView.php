<?php
namespace DafCore\Components\Routing;

use DafCore\Component;
use DafCore\IViewManager;
use DafCore\Views\Components\SystemComponent;

/**
 * @daf-summary Wraps its child content with the currently selected layout from `IViewManager`.
 * 
 * @daf-note Cascades a `PageCallback` that returns this component child content, enabling layout composition via `PageView`.
 */
final class LayoutView extends Component
{
    public \Closure $PageCallback;
    private IViewManager $viewManager;

    public function OnLoad(): void
    {
        $this->viewManager = $this->Inject(IViewManager::class);
    }

    public function Render(): string
    {
        $layout = $this->viewManager->GetLayout();
        $layoutComponent = new SystemComponent($layout);
        $layoutComponent->Cascades['PageCallback'] = ['for' => PageView::class, 'value' => fn()=> $this->RenderChildContent()];

        return $layoutComponent->Render();
    }
}
