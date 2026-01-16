<?php
namespace DafDb;
use DafGlobals\Collections\Collection;
use DafGlobals\Collections\ICollection;

/**
 * Class JsonRepository
 *
 * This class provides a repository for JSON data. It implements the IRepository With, 
 * Countable, and JsonSerializable interfaces to provide various ways to interact 
 * with the data. The class supports operations such as adding, finding, filtering, and removing 
 * items. It also supports saving the data to a file and loading it from a file.
 *
 * @var array<int, mixed>
 * @package DafDb
 */
class JsonRepository implements ICollection {
    private const DEFAULT_STRUCT = "{\n    \"id\":0,\n    \"collection\":[]\n}";

    protected array $data;
    protected string $filename;
    protected array $options = [];
    
    public function __construct(string $fileName, array $options){
        $this->filename = $fileName;
        $this->options = [...$options];
        $this->createFileIfNotExist();

        $this->loadData();
    }

    private function createFileIfNotExist(): void
    {
        if (file_exists($this->filename)) {
            return;
        }

        file_put_contents($this->filename, self::DEFAULT_STRUCT, LOCK_EX);
    }

    protected function loadData(): void
    {
        $raw = @file_get_contents($this->filename);
        $data = $this->decodeStore($raw);

        $this->data['id'] = $data['id'];
        if (!isset($this->options['model'])) {
            $this->data['collection'] = $data['collection'];
            return;
        }

        $this->data['collection'] = [];
        $model = $this->options['model'];
        foreach ($data['collection'] as $key => $item) {
            $payload = is_object($item) ? (array) $item : $item;
            $this->data['collection'][$key] = new $model($payload);
        }
    }

    private function decodeStore(?string $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return $this->defaultStore();
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $this->defaultStore();
        }

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $collection = $data['collection'] ?? [];
        if (!is_array($collection)) {
            $collection = [];
        }

        return [
            'id' => $id,
            'collection' => array_values($collection),
        ];
    }

    private function defaultStore(): array
    {
        return ['id' => 0, 'collection' => []];
    }

    private function persist(): void
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT);
        file_put_contents($this->filename, $json, LOCK_EX);
    }

    function SaveChanges() {
        $this->persist();
    }

    function Add(mixed $item): mixed {
        if(isset($this->options['auto_increment'])){
            $item->{$this->options['auto_increment']} = ++$this->data['id'];
        }
        
        if(isset($this->options['auto_guid'])){ // auto generate guid
            $item->{$this->options['auto_guid']} = uniqid();
        }
        $this->data['collection'][] = $item;
        return $item;
    }
    function Remove(callable $callback) : void {
        foreach($this->data['collection'] as $key => $item){
            if($callback($item) === true){
                unset($this->data['collection'][$key]);
                $this->data['collection'] = array_values($this->data['collection']);
                return;
            }
        }
    }
    function RemoveMany(callable $func) {
        foreach($this->data['collection'] as $key => $item){
            if($func($item) === true){
                unset($this->data['collection'][$key]);
            }
        }
        $this->data['collection'] = array_values($this->data['collection']);
    }

    function Clear() : void {
        $this->data['collection'] = [];
    }
    function Skip(int $length): ICollection
    {
        return new Collection(array_slice($this->data['collection'], $length));
    }
    function Take(int $length): ICollection
    {
        return new Collection(array_slice($this->data['collection'], 0, $length));
    }
    function Any(callable $func = null) : bool {
        if(is_null($func)) return $this->Count() > 0;
        foreach ($this->data['collection'] as $item) {
            if ($func($item) === true) {
                return true;
            }
        }
        return false;
    }
    function Count(callable $callback = null): int {
        if(is_null($callback)) return count($this->data['collection']);

        $filtered = array_filter($this->data['collection'], $callback);
        return count($filtered);
    }
    function Map(callable $func) : ICollection {
        return new Collection(array_map($func, $this->data['collection']));
    }
    function ForEach(callable $callback) : void {
        foreach($this->data['collection'] as $item){
            $callback($item);
        }
    }
    function Reverse() : ICollection {
        return new Collection(array_reverse($this->data['collection']));
    }
    function FirstOrDefault(callable $callback = null): mixed {
        if(is_null($callback)){
            return reset($this->data['collection']);
        }
       
        return $this->SingleOrDefault($callback);
    }
    function SingleOrDefault(callable $callback = null): mixed {
        foreach($this->data['collection'] as $item){
            if($callback($item) === true)
                return $item;
        }
        return null;
    }
    function Where(callable $callback) : ICollection {
        return new Collection(array_filter($this->data['collection'], $callback));
    }
    function FindKey(callable $callback): int|string|null
    {
        foreach ($this->data['collection'] as $key => $item) {
            if ($callback($item, $key) === true) {
                return $key;
            }
        }
        return null;
    }
    function ToArray(): array {
        return $this->data['collection'];
    }

    function Last() : mixed {
        return end($this->data['collection']);
    }





    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data['collection']);
    }
    function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data['collection'][] = $value;
        } else {
            $this->data['collection'][$offset] = $value;
        }
    }
    function offsetExists($offset): bool
    {
        return isset($this->data['collection'][$offset]);
    }
    function offsetUnset($offset): void
    {
        unset($this->data['collection'][$offset]);
    }
    function offsetGet(mixed $offset): mixed
    {
        return isset($this->data['collection'][$offset]) ? $this->data['collection'][$offset] : null;
    }


    public function __toString(){
        return json_encode($this->data['collection']);
    }
    public function __debugInfo(){
        return $this->data['collection'];
    }
    public function jsonSerialize() {
        return $this->data['collection'];
    }
}
