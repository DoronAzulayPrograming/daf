<?php
namespace DafCore\Components\Routing;

use DafCore\Component;
use DafCore\Views\Components\ComponentRegistry;
use DafCore\Views\Components\ComponentsParser;
use DafCore\Views\Components\SystemComponent;

/**
 * @daf-summary Pre-renders a selected child component in the host template while preserving the normal render order for all other components.
 */
final class HostComponent extends Component
{
    // Component name or type to pre-render first (first match only)
    public string $StartWith;

    public function Render(): string
    {
        $renderScope = [];
        $strToRender = "";

        $templatePath = $this->_->GetComponentTemplatePath();

        if (self::$extractUsing) {
            $_DAF_source = @file_get_contents($templatePath) ?: '';
            $uses = $this->_->daf_extract_uses($_DAF_source);
            foreach ($uses as $use) {
                ComponentRegistry::AddNamespaces($use);
            }
        }

        [$strToRender, $renderScope] = $this->_->RenderTemplateWithView($this, $templatePath);

        $cacheKey = $templatePath . ':' . sha1($strToRender);
        $comps = ComponentsParser::MakeComponentsCachedKey($cacheKey, $strToRender);

        if (empty($comps)) {
            return $strToRender;
        }

        // Prepare all components once (same as normal flow setup).
        foreach ($comps as &$item) {
            /** @var SystemComponent $c */
            $c = $item['component'];
            $c->Parent = $this->_;

            if (!empty($this->_->Cascades)) {
                $c->Cascaded = $this->_->ResolveCascadeFor($c);
            } else {
                $c->Cascaded = $this->_->Cascaded;
            }

            $this->_->ApplyScopeToComponent($c, $renderScope);
        }
        unset($item);

        // Render target component first, but DO NOT place it yet.
        $preRendered = [];
        $targetIndex = null;

        foreach ($comps as $idx => $item) {
            /** @var SystemComponent $c */
            $c = $item['component'];

            $isTarget = $c->Name === $this->StartWith || $c->GetType() === $this->StartWith;

            if ($isTarget) {
                $targetIndex = $idx;
                $preRendered[$idx] = $c->Render();
                break; // first match only
            }
        }

        // Normal output flow; inject pre-rendered target at original location.
        $out = '';
        $last = 0;

        foreach ($comps as $idx => $item) {
            /** @var SystemComponent $c */
            $c = $item['component'];

            $start = $item['start'];
            $end = $item['end'];

            $out .= substr($strToRender, $last, $start - $last);

            if ($idx === $targetIndex) {
                $out .= $preRendered[$idx];
            } else {
                $out .= $c->Render();
            }

            $last = $end;
        }

        $out .= substr($strToRender, $last);
        return $out;
    }

}
