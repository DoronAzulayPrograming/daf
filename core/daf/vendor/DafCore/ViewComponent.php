<?php
namespace DafCore;

class ViewComponent implements IComponent{
   
    /** Wrap a component with a restricted, template-friendly API.
     * @param Component $_
     */
    public function __construct(protected Component $_) {}

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
    public function GetChildren(): array { return array_map(fn($c) => new ViewComponent($c), $this->_->GetChildren()); }

    /** Filter wrapped children by component path.
     * @param string $type file path
     * @return array ViewComponent[]
     */
    public function GetChildrenOfType(string $type): array
    {
        $kids = $this->_->GetChildrenOfType($type);
        return array_map(fn($c) => new ViewComponent($c), $kids);
    }

    /** Render all wrapped children of a given type.
     * @param string $type file path
     * @return void
     */
    public function RenderChildrenOfType(string $type): void { foreach($this->GetChildrenOfType($type) as /** @var ViewComponent $c */ $c) echo $c->Render(); }

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

    /** Render this component and its nested components.
     * @return string
     */
    public function Render(): string { return $this->_->Render(); }
}
