Building a new codeclimate image
--------------------------------

From the root directory of the phan installation (Change build tag if publishing)

```sh
	sudo docker build -t phan:0.10.3 -f plugins/codeclimate/Dockerfile .
```


Running the codeclimate image
-----------------------------

The following example uses the phan codeclimate image to analyze the phan checkout.
By running it from a different folder passing in a different config.json to `-v`, options can be changed.

```sh
sudo docker run -v $PWD:/code:ro -v $PWD:/code:ro -v $PWD/plugins/codeclimate/config-example.json:/config.json phan:0.10.3
```

Future work
-----------

Use settings from the project's .phan/config.php if the docker image can find it in /code/.phan/config.php
If any options are provided in .codeclimate.yml, make them override .phan/config.php.
