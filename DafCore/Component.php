<?php
namespace DafCore;

class Component implements IComponent{
    public static bool $UseNewUsingSystem = true;
   
    /** Wrap a component with a restricted, template-friendly API.
     * @param SystemComponent $_
     */
    public function __construct(protected SystemComponent $_) { }

    /** Register namespaces for component discovery.
     * @param string|array $useing string|string[] of file path
     * @return void
     */
    public function Use(string|array $useing): void{ $this->_->Use($useing); }

    /** Resolve a service from the DI container.
     * @param string $type dependency key 
     * @return mixed dependency
     */
    public function Inject(string $type): mixed{ return $this->_->Inject($type); }

    /** Read a parameter (explicit or cascaded), optionally type-check.
     * @param string $name parameter name
     * @param string|null $type file path or null
     * @return mixed
     */
    public function Parameter(string $name, string $type = null): mixed{ return $this->_->Parameter($name, $type); }

    /** Read a parameter and fail if missing or null.
     * @param string $name parameter name
     * @param string|null $type file path or null
     * @return mixed
     */
    public function RequiredParameter(string $name, string $type = null): mixed { return $this->_->RequiredParameter($name, $type); }

    public function GetType(): string { return $this->_->GetType(); }

    /** Provide a cascading value to descendants.
     * @param string $key 
     * @param mixed $value
     * @param array|string $for
     * @return void
     */
    public function Cascade(string $key, mixed $value, array|string $for = 'all'):void { $this->_->Cascade($key, $value, $for); }

    /** Return the raw child content string.
     * @return string
     */
    public function RenderChildContent(): string { return $this->_->RenderChildContent(); }

    /** Return wrapped direct child components.
     * @return array
     */
    public function GetChildren(): array { return array_map(fn($c) => $c['c']->ViewComponent, $this->_->Children); }

    /** Filter wrapped children by component path.
     * @param string $type file path
     * @return array Component[]
     */
    public function GetChildrenOfType(string $type): array
    {
        $kids = $this->_->GetChildrenOfType($type);
        return array_values(array_map(fn($c) => $c->ViewComponent, $kids));
    }

    /** Render all wrapped children of a given type.
     * @param string $type file path
     * @return void
     */
    public function RenderChildrenOfType(string $type): string { $out = ""; foreach($this->GetChildrenOfType($type) as /** @var Component $c */ $c) $out .= $c->Render(); return $out; }

    /** Render attributes as an HTML string (escaped).
     * @return string
     */
    public function RenderAttributes(): string { return $this->_->RenderAttributes(); }

    /** Get a single attribute value.
     * @param string $name attribute name
     * @return string|null attribute value or null
     */
    public function GetAttribute(string $name): string|null { return $this->_->GetAttribute($name); }

    /** Get all attributes.
     * @return array attribute array
     */
    public function GetAttributes(): array { return $this->_->GetAttributes(); }

    /** Replace or set multiple attributes.
     * @param array $attrs attribute array
     * @return void
     */
    public function SetAttributes(array $attrs): void { $this->_->SetAttributes($attrs); }

    /** Merge attributes to the end.
     * @param array $attrs attribute array
     * @return void
     */
    public function AddAttributesToEnd(array $attrs): void { $this->_->AddAttributes($attrs); }

    /** Merge attributes to the start.
     * @param array $attrs attribute array
     * @return void
     */
    public function AddAttributesToStart(array $attrs): void { $this->_->AddAttributes($attrs, 'start'); }

    public function OnLoad():void {}


    /** Render this component and its nested components.
     * @return string
     */
    public function Render(): string
    {
        $renderScope = [];
        $strToRender = "";

        $templatePath = $this->_->GetComponentTemplatePath();
        

        if (str_starts_with($templatePath, "phar://") || str_starts_with($templatePath, "vendor") || str_starts_with($templatePath, Application::$BaseFolder)) { // Component::Exists($templatePath)

            if(self::$UseNewUsingSystem){
                // NEW: read source + extract imports BEFORE include (execute once only)
                $_DAF_source = @file_get_contents($templatePath) ?: '';
                $uses = $this->_->daf_extract_uses($_DAF_source);
                foreach ($uses as $key => $use) {
                    SystemComponent::AddNamespaces($use);
                }
            }
            
            [$strToRender, $renderScope] = $this->_->RenderTemplateWithView($this, $templatePath);
        }
        else { $strToRender = $this->_->Path; }

        $cacheKey = $templatePath . ':' . strlen($strToRender);
        $comps = CParser::MakeComponentsCachedKey($cacheKey, $strToRender);

        if (!empty($comps)) {
            $out = '';
            $last = 0;

            foreach ($comps as $item) {
                /** @var SystemComponent $c */
                $c = $item['component'];

                if (!empty($this->_->Cascades)) {
                    $c->Cascaded = $this->_->ResolveCascadeFor($c);
                } else {
                    $c->Cascaded = $this->_->Cascaded;
                }
                $this->_->applyScopeToComponent($c, $renderScope);

                $start = $item['start'];
                $end = $item['end']; // absolute end index

                $out .= substr($strToRender, $last, $start - $last);

                try {
                $out .= $c->Render();
                } catch (\Throwable $th) {
                echo $th->getMessage();
                }

                $last = $end;
            }

            $out .= substr($strToRender, $last);
            $strToRender = $out;
        }


        return $strToRender;
    }
}
