<?php
// Test basic get/set hooks
class User {
    private string $firstName;
    private string $lastName;

    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
        set {
            [$this->firstName, $this->lastName] = explode(' ', $value, 2);
        }
    }
}

// Virtual property with no backing
class Temperature {
    private float $kelvin = 0;

    public float $celsius {
        get => $this->kelvin - 273.15;
        set => $this->kelvin = $value + 273.15;
    }

    public float $fahrenheit {
        get => ($this->kelvin - 273.15) * 9/5 + 32;
        set => $this->kelvin = ($value - 32) * 5/9 + 273.15;
    }
}

// Test usage
$user = new User();
$user->fullName = "John Doe";
echo $user->fullName;

$temp = new Temperature();
$temp->celsius = 25;
echo $temp->fahrenheit;
