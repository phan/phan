<?php

interface OperationalInterface
{
    public function isOperational(): bool;
}

interface VehicleInterface extends OperationalInterface
{
    public function move(int $distance): void;
    public function setSpeed(int $speed): void;
    public function refuel(): void;
    public function park(): void;
}

interface MotorizedInterface extends VehicleInterface
{
    public function toggleMotor(): void;
    public function toggleClutch(): void;
    public function drive(int $distance): void;
}

interface SailableInterface extends VehicleInterface
{
    public function sail(int $distance): void;
}

interface PersonalVehicleInterface extends VehicleInterface
{
    public function radio(float|null $station): void;
}

interface CommercialVehicleInterface extends VehicleInterface, MotorizedInterface
{
    public function transport(mixed &$cargo): void;
    public function unload(): mixed;
}

trait MovableTrait
{
    protected int $speed = 0;
    public function move(int $distance): void {
        echo "Moving {$distance} units at speed {$this->speed}\n";
    }
    public function setSpeed(int $speed): void {
        $this->speed = $speed;
    }
}

trait VehicleOperationTrait
{
    protected bool $operational = true;
    protected string $type = 'unknown';
    public function isOperational(): bool {
        return $this->operational;
    }
    public function refuel(): void {
        echo "The {$this->type} is being refueled...\n";
    }
    public function park(): void {
        echo "The {$this->type} is being parked...\n";
    }
}

trait MotorizedTrait
{
    use MovableTrait;
    protected bool $motorOn = false;
    protected bool $clutchEngaged = false;
    public function toggleMotor(): void {
        $this->motorOn = !$this->motorOn;
    }
    public function toggleClutch(): void {
        $this->clutchEngaged = !$this->clutchEngaged;
    }
    public function drive(int $distance): void {
        if (!$this->motorOn) {
            echo "Motor is off, cannot drive\n";
            return;
        }
        if ($this->clutchEngaged) {
            echo "Clutch is engaged, cannot drive\n";
            return;
        }
        $this->move($distance);
    }
}

trait SailingTrait
{
    use MovableTrait;
    protected bool $isInWater = true;
    public function sail(int $distance): void {
        if ($this->isInWater) {
            $this->move($distance);
        } else {
            echo "Cannot sail, not in water\n";
        }

    }
}

abstract class Vehicle implements VehicleInterface {
    use VehicleOperationTrait;
    protected bool $operational = true;
    public function __construct(
        protected string $type
    ) {}
    abstract public function honk(): void;
}

class Car extends Vehicle implements PersonalVehicleInterface, MotorizedInterface{
    use MotorizedTrait;
    private bool $radioOn = false;
    private float $radioStation = 0.0;
    public function __construct(private string $model) {
        parent::__construct('car');
        $this->setSpeed(5);
    }
    public function honk(): void {
        echo "Beep!\n";
    }
    public function radio(float|null $station): void {
        if (is_float($station)) {
            $this->radioStation = $station;
            $this->radioOn = true;
            echo "Playing station {$station}\n";
        } else {
            $this->radioOn = false;
            echo "Radio is off\n";
        }
    }
}

final class Taxi extends Car implements CommercialVehicleInterface{
    protected mixed $passengers = [];
    public function __construct() {
        parent::__construct('crown victoria');
        $this->setSpeed(7);
    }
    public function transport(mixed &$passengers): void {
        $this->passengers = $passengers;
    }
    public function unload(): mixed {
        $passengers = $this->passengers;
        $this->passengers = null;
        return $passengers;
    }
    public function honk(): void {
        echo "Several loud honks (taxis are impatient)\n";
    }
}

class SailBoat extends Vehicle implements VehicleInterface, SailableInterface {
    use SailingTrait;
    public function __construct(public string $type) {
        parent::__construct('sailboat');
        $this->setSpeed(2);
    }
    public function honk(): void {
        echo "Klaxon\n";
    }
}

class MotorBoat extends Vehicle implements VehicleInterface, SailableInterface, MotorizedInterface {
    use SailingTrait;
    use MotorizedTrait;
    public function __construct(public string $type){
        parent::__construct('motorboat');
        $this->setSpeed(3);
    }
    public function honk(): void {
        echo "Foghorn\n";
    }
}

// Anonymous class example
$anonymousVehicle = new class('mystery') extends Vehicle {
    use VehicleOperationTrait;
    use MotorizedTrait;
    protected string $type;
    public function honk(): void {
        echo "Some mysterious sound\n";
    }
};
