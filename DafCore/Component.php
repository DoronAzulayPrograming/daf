<?php
namespace DafCore;

use DafCore\Views\Components\SystemComponent;
use DafCore\Views\Components\ComponentsParser;
use DafCore\Views\Components\ComponentRegistry;


class Component implements IComponent {
    protected static bool $extractUsing = true;
    public static function DisableExtractUsing(): void { self::$extractUsing = false; }
    
    public string $Id;
    
    /** Wrap a component with a restricted, template-friendly API.
     * @param SystemComponent $_
     */
    public function __construct(protected SystemComponent $_) { $this->Id = $_->Id; }


    public function GetParent(): ?Component { return $this->_->Parent?->ViewComponent; }

    /** Register namespaces for component discovery.
     * @param string|array $useing string|string[] of file path
     * @return void
     */
    public function Use(string|array $useing): void{ ComponentRegistry::AddNamespaces($useing); }

    /** Resolve a service from the DI container.
     * @param string $type dependency key 
     * @return mixed dependency
     */
    public function Inject(string $type): mixed{ return ServicesProvidor::$DI->getOne($type); }

    /** Read a parameter (explicit or cascaded), optionally type-check.
     * @param string $name parameter name
     * @param string|null $type file path or null
     * @return mixed
     */
    public function Parameter(string $name, ?string $type = null): mixed {
        $val = null;
        $this->_->TryGetParameter($val, $name, $type);
        return $val;
    }

    /** Read a parameter and fail if missing or null.
     * @param string $name parameter name
     * @param string|null $type file path or null
     * @return mixed
     */
    public function RequiredParameter(string $name, ?string $type = null): mixed
    {
        $val = null;
        if (!$this->_->TryGetParameter($val, $name, $type))
            die("Required parameter $name is not set in component {$this->GetType()}");
        
        if ($val === null)
            die("Required Parameter $name is not set in component {$this->GetType()}");
    
        return $val;
    }

    public function GetType(): string { return $this->_->GetType(); }

    /** Provide a cascading value to descendants.
     * @param string $key 
     * @param mixed $value
     * @param array|string $for
     * @return void
     */

    public function Cascade(string $key, mixed $value = null, array|string $for = 'all'):void { $this->_->Cascades[$key] = ['for' => $for, 'value' => $value ?? $this->Parameter($key)]; }

    /** Return the raw child content string.
     * @return string
     */
    public function RenderChildContent(): string { 
        $this->_->EnsureChildrenBuilt();

        if (empty($this->_->Children)) {
            return $this->_->ChildContent;
        }

        $parts = [];
        $last = 0;

        foreach ($this->_->Children as $it) {
            /** @var SystemComponent $child */
            $child = $it['c'];
            $start = $it['start'];
            $end   = $it['end'];

            // Only add text chunk if needed
            if ($start > $last) {
                $parts[] = substr($this->_->ChildContent, $last, $start - $last);
            }

            // resolve cascades NOW (after parent OnLoad/template ran)
            if (!empty($this->Cascades)) {
                $child->Cascaded = $this->_->ResolveCascadeFor($child);
            } else {
                $child->Cascaded = $this->_->Cascaded;
            }

            $parts[] = $child->Render();
            $last = $end;
        }

        // tail text
        $tailLen = strlen($this->_->ChildContent) - $last;
        if ($tailLen > 0) {
            $parts[] = substr($this->_->ChildContent, $last, $tailLen);
        }

        return implode('', $parts);
    }

    /** Return wrapped direct child components.
     * @return array
     */
    public function GetChildren(): array { return array_map(fn($c) => $c->ViewComponent, $this->_->GetChildren()); }

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
    public function RenderChildrenOfType(string $type): string {
        $out = "";
        $childs = $this->GetChildrenOfType($type);
        foreach ($childs as /** @var Component $c */ $c) $out .= $c->Render();
        return $out;
    }

    /** Render attributes as an HTML string (escaped).
     * @return string
     */
    public function RenderAttributes(): string {
        $attrs = "";
        foreach ($this->_->Attributes as $key => $value) {
            if ($value === null) continue;
            $safe = htmlspecialchars((string) $value, ENT_QUOTES);
            $attrs .= "$key='$safe' ";
        }
        return $attrs;
    }



    /** Get a single attribute value.
     * @param string $name attribute name
     * @return string|null attribute value or null
     */
    public function GetAttribute(string $name): string|null {
        return $this->_->Attributes[$name] ?? null;
    }

    /** Get all attributes.
     * @return array attribute array
     */
    public function GetAttributes(): array { return $this->_->Attributes; }

    /** Replace or set multiple attributes.
     * @param array $attrs attribute array
     * @return void
     */
    public function SetAttributes(array $attrs): void {
        foreach ($attrs as $key => $value) {
            $this->_->Attributes[$key] = $value;
        }
    }

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

    /**
     * Called on component create.
     * @return void
     */
    public function OnLoad():void {}

    /**
     * Called on component create before OnLoad.
     * @return void
     */
    public function Load():void { 
        $this->_->AutoBindDeclaredParameters($this);
        
        $this->OnLoad();
    }


    /** Return pre render daf view string ready to daf parser.
     * @return string
    */
    public function OnRender(): string {
        $renderScope = [];
        $strToRender = "";

        $templatePath = $this->_->TryGetComponentTemplatePath();

        if($templatePath === null){
            if(self::$extractUsing){
                $uses = $this->_->daf_extract_uses($this->_->SourceText);
                foreach ($uses as $use) {
                    ComponentRegistry::AddNamespaces($use);
                }
            }
            return $this->_->SourceText;
        }

        if(self::$extractUsing){
            $_DAF_source = @file_get_contents($templatePath) ?: '';
            $uses = $this->_->daf_extract_uses($_DAF_source);
            foreach ($uses as $use) {
                ComponentRegistry::AddNamespaces($use);
            }
        }
        
        [$strToRender, $renderScope] = $this->_->RenderTemplateWithView($this, $templatePath);

        $this->_->ScopesFromRender = $renderScope;
        return $strToRender;
    }

    /** Render this component and its nested components.
     * @return string
    */
    public function Render(): string
    {
        $strToRender = $this->OnRender();
        $renderScope = $this->_->ScopesFromRender;

        $renderScope['this'] = $this;
        
        $templatePath = $this->_->TryGetComponentTemplatePath();
        if($templatePath === null) $templatePath = "System/Fragment";
        
        $cacheKey = $templatePath . ':' . sha1($strToRender);
        $comps = ComponentsParser::MakeComponentsCachedKey($cacheKey, $strToRender);

        if (!empty($comps)) {
            $out = '';
            $last = 0;

            foreach ($comps as $item) {
                /** @var SystemComponent $c */
                $c = $item['component'];
                $c->Parent = $this->_;

                if (!empty($this->_->Cascades)) {
                    $c->Cascaded = $this->_->ResolveCascadeFor($c);
                } else {
                    $c->Cascaded = $this->_->Cascaded;
                }
                $this->_->ApplyScopeToComponent($c, $renderScope);

                $start = $item['start'];
                $end = $item['end']; // absolute end index

                $out .= substr($strToRender, $last, $start - $last);

                $out .= $c->Render();

                $last = $end;
            }

            $out .= substr($strToRender, $last);
            $strToRender = $out;
        }


        return $strToRender;
    }
}
