<?php

require __DIR__ . '/../vendor/autoload.php';

interface IFixture {}

class FixtureOne implements IFixture
{
	public int $number;

	public function __construct( int $number = 1 )
	{
		$this->number = $number;
	}
}

class FixtureWithInterface
{
	public \IFixture $fixture;
	public int $number;

	public function __construct(\IFixture $fixture, int $number = 1)
	{
		$this->fixture = $fixture;
		$this->number = $number;
	}

	public function method( string $proof, \IFixture $fixture, int $times = 101 ): array
	{
		return [ $proof, $fixture, $times ];
	}

	public static function staticMethod( string $proof, \IFixture $fixture, int $times = 101 ): array
	{
		return [ $proof, $fixture, $times ];
	}
}

class FixtureVariadic
{
	public ?string $address;
	public array $numbers;

	public function __construct( ?string $address, int ...$numbers )
	{
		$this->address = $address;
		$this->numbers = $numbers;
	}
}
