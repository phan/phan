.PHONY: image

MKFILE_PATH := $(abspath $(lastword $(MAKEFILE_LIST)))
DOCKERFILE := $(dir $(MKFILE_PATH))Dockerfile
DOCKER_CONTEXT := $(abspath $(dir $(MKFILE_PATH))../..)

IMAGE_NAME ?= codeclimate/codeclimate-phan

image:
	docker build --rm -t $(IMAGE_NAME) -f "$(DOCKERFILE)" "$(DOCKER_CONTEXT)"
