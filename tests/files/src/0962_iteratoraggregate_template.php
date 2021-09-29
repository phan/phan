<?php

class UserModel {
    public $id;
    public function __construct(int $id)
    {
        $this->id = $id;
    }
}

/**
 * @template T
 */
class Collection implements \IteratorAggregate {
    /**
     * @var T[]
     */
    public $modelArray;

    /**
     * @param T[] $modelArray
     */
    public function __construct($modelArray = [])
    {
        $this->modelArray = $modelArray;
    }

    /**
     * @return \Iterator<int, T>
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->modelArray);
    }
}

class UserRepository {
    /**
     * @return Collection<UserModel>
     */
    public static function findByString(string $value): Collection
    {
        return new Collection([0 => new UserModel(0), 1 => new UserModel(1)]);
    }
}


$users = UserRepository::findByString('something');
$iterator = $users->getIterator();
'@phan-debug-var $iterator, $users';
foreach ($users as $key => $user) {
    '@phan-debug-var $key, $user';
    echo $user->id . "\n";
}
foreach ($iterator as $key => $user) {
    '@phan-debug-var $key, $user';
    echo $user->id . "\n";
}
