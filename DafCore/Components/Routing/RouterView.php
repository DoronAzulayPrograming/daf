<?php
namespace DafCore\Components\Routing;

use DafCore\Response;
use DafCore\Component;
use DafCore\ApplicationContext;

/**
 * @daf-summary Uses the pre-resolved `Route` and renders either `<Found>` or `<NotFound>` child content.
 * 
 * @daf-note Routing and pipeline execution are completed earlier in `Router::Resolve()`.
 * @daf-note When route is not found, it sets the HTTP status code before rendering `<NotFound>` content.
 */
class RouterView extends Component
{
    private ApplicationContext $appContext;

    public function OnLoad(): void
    {
        $this->appContext = $this->Inject(ApplicationContext::class);
    }

    public function Render(): string
    {
        if (!$this->appContext->RouteContext->Found) {
            $this->appContext->Response->Status(
                $this->appContext->RouteContext->StatusCode ?: Response::HTTP_NOT_FOUND
            );

            $notFound = $this->GetChildrenOfType(NotFound::class);
            if (empty($notFound)) {
                return "404 - Route Not Found";
            }

            return $notFound[0]->RenderChildContent();
        }

        $found = $this->GetChildrenOfType(Found::class);
        if (empty($found)) {
            return "";
        }

        return $found[0]->RenderChildContent();
    }
}
