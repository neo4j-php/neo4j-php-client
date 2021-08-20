"""
Executed in PHP driver container.
Responsible for building driver and test backend.
"""
import subprocess
import os


def run(args):
    subprocess.run(
        args, universal_newlines=True, stderr=subprocess.STDOUT, check=True)


if __name__ == "__main__" and "TEST_SKIP_BUILD" not in os.environ:

    err = open("/artifacts/build.log", "w")
    out = open("/artifacts/build.log", "w")

    subprocess.check_call(["composer","--working-dir=/driver" ,"install"], stdout=out, stderr=err)
