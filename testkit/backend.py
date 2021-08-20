"""
Executed in PHP driver container.
Responsible for starting the test backend.
"""
import os, subprocess


if __name__ == "__main__":
    err = open("/artifacts/backenderr.log", "w")
    out = open("/artifacts/backendout.log", "w")
    subprocess.check_call(["php", "testkit-backend/index.php"], stdout=out, stderr=err)


