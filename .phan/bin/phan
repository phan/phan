#!/bin/sh

# Root directory of project
export ROOT=`git rev-parse --show-toplevel`

# Phan's directory for executables
export BIN=$ROOT/.phan/bin

# Phan's data directory
export DATA=$ROOT/.phan/data
mkdir -p $DATA;

# Go to the root of this git repo
pushd $ROOT > /dev/null

    # Get the current hash of HEAD
    export REV=`git rev-parse HEAD`

    # Create the data directory for this run if it
    # doesn't exist yet
    export RUN=$DATA/$REV
    mkdir -p $RUN

    $BIN/mkfilelist > $RUN/files

    # Run the analysis, emitting output to the console
    # and using a previous state file.
    phan \
        --progress-bar \
        --project-root-directory $ROOT \
        --output $RUN/issues && exit $?

    # Re-link the latest directory
    rm -f $ROOT/.phan/data/latest
    ln -s $RUN $DATA/latest

    # Output any issues that were found
    cat $RUN/issues

popd > /dev/null
