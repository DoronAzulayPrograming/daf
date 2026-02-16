<?php 
namespace DafDb\Query;

final class SqlCommand
{
    public function __construct(public string $Query, public array $Parameters = []) {}
}