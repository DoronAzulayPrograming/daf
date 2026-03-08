<?php
namespace DafCore\Components\Routing;

use DafCore\Component;

/**
 * @daf-summary Container for content rendered only when route not found. Must be a direct child of `<RouterView>`.
 */
final class NotFound extends Component
{
    public function OnLoad(): void {
        $parent = $this->GetParent();
        if($parent === null){
            throw new \Exception("Found can live only inside RouterView Component", 1);
        }else if(!($parent instanceof RouterView)){
            throw new \Exception("Found can live only inside RouterView Component", 1);
        }
    }
    public function Render(): string { return $this->RenderChildContent(); }
}
