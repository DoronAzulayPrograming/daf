<?php
namespace DafDb;
use DafGlobals\Collection;
use DafGlobals\ICollection;

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
    protected array $data;
    private string $struct;
    protected string $filename;
    protected array $options = [];
    
    public function __construct(string $fileName, array $options){
        $this->filename = $fileName;
        $this->options = [...$options];
        $this->createFileIfNotExist();

        $this->loadData();
    }

    private function createFileIfNotExist() {
        $this->struct = "{
    \"id\":0,
    \"collection\":[]
}";

        if(file_exists($this->filename)) return;

        $handle = fopen($this->filename, 'a+');

        fwrite($handle, $this->struct);
        fclose($handle);
    }

    protected function loadData() {
        $this->data['id'] = 0;
        $this->data['collection']= [];
        
        $json = file_get_contents($this->filename);
        if(is_bool($json) || !strlen($json))
            $data = json_decode($this->struct, true);
        else{
            $data = json_decode($json, true);
        }
        
        $this->data['id'] = $data['id'];
        foreach($data['collection'] as $key => $item){
            $model = $this->options['model'];
            $obj = new $model($item);
            $this->data['collection'][$key] = $obj;
        }
    }

    function SaveData() {
        $json = json_encode($this->data, JSON_PRETTY_PRINT);
        file_put_contents($this->filename, $json);
    }

    function Add(mixed $item): void {
        if(isset($this->options['auto_increment'])){
            $item->{$this->options['auto_increment']} = ++$this->data['id'];
        }
        else if(isset($this->options['auto_guid'])){ // auto generate guid
            $item->{$this->options['auto_guid']} = uniqid();
        }
        $this->data['collection'][] = $item;
        return $item;
    }
    function Remove(callable $callback) : void {
        foreach($this->data['collection'] as $key => $item){
            if($callback($item) === true){
                unset($this->data['collection'][$key]);
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
        $o = $this->SingleOrDefault($func);
        return !is_null($o);
    }
    function Count(callable $callback = null): int {
        if(is_null($callback)) return count($this->data['collection']);

        $count = $this->Where($callback);
        return $count ?? 0;
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

        $this->SaveData();
    }
    function offsetExists($offset): bool
    {
        return isset($this->data['collection'][$offset]);
    }
    function offsetUnset($offset): void
    {
        unset($this->data['collection'][$offset]);
        $this->SaveData();
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