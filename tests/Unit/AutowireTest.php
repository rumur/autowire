<?php

namespace Rumur\Autowire\Test\Unit;

use PHPUnit\Framework\TestCase;
use Rumur\Autowiring\Autowire;
use Rumur\Autowiring\Exceptions\NotInstantiable;

class AutowireTest extends TestCase
{
	public function testCanResolveItselfAsDependency(): void
	{
		$aw = Autowire::create();

		$aw->bind( 'clone', function ( Autowire $aw ) {
			return $aw;
		} );

		$cloned = $aw->make( 'clone' );

		$this->assertSame( $aw, $cloned );
	}

	public function testCanResolve(): void
	{
		$aw = Autowire::create();

		$aw->bind( \IFixture::class, \FixtureOne::class );

		$resolved_first  = $aw->make( \FixtureWithInterface::class );
		$resolved_second = $aw->make( \FixtureWithInterface::class );

		$this->assertNotSame( $resolved_first->fixture, $resolved_second->fixture );
	}

	public function testCanResolveMethods(): void
	{
		$aw = Autowire::create();

		$aw->singleton( \IFixture::class, \FixtureOne::class );

		$resolved = $aw->make( \FixtureWithInterface::class );

		$cb_result = $aw->call( function ( \IFixture $fixture ) {
			return $fixture;
		} );

		$method_result = $aw->call( [ $resolved, 'method' ], [ 'proof' => 'approved'] );
		$method_static = $aw->call( '\\FixtureWithInterface::staticMethod', [ 'proof' => 'approvedStatic' ] );

		$this->assertEquals( $resolved->fixture, $cb_result );
		$this->assertContains( $resolved->fixture, $method_static );
		$this->assertContains( 'approved', $method_result );
		$this->assertContains( 'approvedStatic', $method_static );

		$this->expectException( NotInstantiable::class );

		$method_result = $aw->call( [ $resolved, 'method' ] );
		$method_static = $aw->call( '\\FixtureWithInterface::staticMethod');
	}

	public function testCanResolveSingletons(): void
	{
		$aw = Autowire::create();

		$aw->singleton( \IFixture::class, \FixtureOne::class );

		$resolved_first  = $aw->make( \IFixture::class );
		$resolved_second = $aw->make( \IFixture::class );

		$this->assertSame( $resolved_first, $resolved_second );
	}

	public function testCanResolveFactory(): void
	{
		$aw = Autowire::create();

		$resolved_before  = $aw->make( \FixtureOne::class );

		$aw->singleton( \IFixture::class, function () {
			return new \FixtureOne( 10 );
		} );

		$resolved_after = $aw->make( \IFixture::class );

		$this->assertNotSame( $resolved_before, $resolved_after );
		$this->assertNotSame( $resolved_before->number, $resolved_after->number );
	}

	public function testCanResolveVariadic(): void
	{
		$aw = Autowire::create();

		$numbers = range( 0, 5 );

		$resolved = $aw->make( \FixtureVariadic::class, [
			'numbers' => $numbers,
		] );

		$this->assertSame( $resolved->numbers, $numbers );
	}
}