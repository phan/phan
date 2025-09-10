<?php

// Trait used correctly, but with no documentation, causes issues to be emitted.
// The expected class elements must be documented.
namespace NS1003a {
	trait T {
		function f(): void {
			$this->m( $this->p );
		}
	}

	class C {
		use T;
		private $p = 123;
		private function m($v): void {
			echo $v;
		}
	}

	// This is incorrect, and should emit some issues, but it currently doesn't.
	class D {
		use T;
	}

	(new C)->f();
	(new D)->f();
}

// Trait used correctly, with documentation for expected class.
namespace NS1003b {
	/** @extends C */
	trait T {
		function f(): void {
			$this->m( $this->p );
		}
	}

	class C {
		use T;
		private $p = 123;
		private function m($v): void {
			echo $v;
		}
	}

	// This is incorrect, and should emit some issues, but it currently doesn't.
	class D {
		use T;
	}

	(new C)->f();
	(new D)->f();
}

// Trait used correctly, with documentation for individual methods/props.
namespace NS1003c {
	/**
	 * @method m($v):void
	 * @property $p
	 */
	trait T {
		function f(): void {
			$this->m( $this->p );
		}
	}

	class C {
		use T;
		private $p = 123;
		private function m($v): void {
			echo $v;
		}
	}

	// This is incorrect, and should emit some issues, but it currently doesn't.
	class D {
		use T;
	}

	(new C)->f();
	(new D)->f();
}
