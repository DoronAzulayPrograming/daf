<?php
namespace DafCore;

interface IComponent{
   /** Render the raw child content string as provided in markup.
    * @return string
    */
   public function RenderChildContent(): string;

   /** Return direct child components.
    * @return array
    */
   public function GetChildren(): array;

   /** Register namespaces for component discovery.
    * @param string|array $useing string|string[] of file path
    * @return void
    */
   public function Use(string|array $useing): void;

   /** Resolve a service from the DI container.
    * @param string $type dependency key
    * @return mixed dependency
    */
   public function Inject(string $type): mixed;

   /** Read a parameter (explicit or cascaded), optionally type-check.
    * @param string $name parameter name
    * @param string|null $type file path or null
    * @return mixed
    */
   public function Parameter(string $name, string $type = null): mixed;

   /** Read a parameter and fail if missing or null.
    * @param string $name parameter name
    * @param string|null $type file path or null
    * @return mixed
    */
   public function RequiredParameter(string $name, string $type = null): mixed;

   public function GetType(): string;

   /** Provide a cascading value to descendants.
    * @param string $key
    * @param mixed $value
    * @param array|string $for
    * @return void
    */
   public function Cascade(string $key, mixed $value, array|string $for = 'all'):void;

   /** Filter children by component path.
    * @param string $type file path
    * @return array
    */
   public function GetChildrenOfType(string $type) : array;

   /** Render all children of a given type.
    * @param string $type file path
    * @return void
    */
   public function RenderChildrenOfType(string $type): string;

   /** Render attributes as an HTML string (escaped).
    * @return string
    */
   public function RenderAttributes(): string;

   /** Get a single attribute value.
    * @param string $name attribute name
    * @return string|null attribute value or null
    */
   public function GetAttribute(string $name): string|null;
   
   /** Get all attributes.
    * @return array attribute array
    */
   public function GetAttributes(): array;

   /** Replace or set multiple attributes.
    * @param array $attrs attribute array
    * @return void
    */
   public function SetAttributes(array $attrs): void;

   /** Merge attributes to the end.
    * @param array $attrs attribute array
    * @return void
    */
   public function AddAttributesToEnd(array $attrs): void;

   /** Merge attributes to the start.
    * @param array $attrs attribute array
    * @return void
    */
   public function AddAttributesToStart(array $attrs): void;

   /** Render this component and its nested components.
    * @return string
    */
   public function Render(): string;
}