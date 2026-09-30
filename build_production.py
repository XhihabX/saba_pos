import os
import subprocess
import sys

def run_command(cmd, description):
    print(f"--> {description}...")
    result = subprocess.run(cmd, shell=True)
    if result.returncode != 0:
        print(f"[ERROR] Error during: {description}")
        sys.exit(1)
    print(f"[OK] Completed: {description}\n")

def main():
    print("==========================================================")
    print("Saba POS 200% Production Build & Deployment Optimizer")
    print("==========================================================\n")

    # 1. Front-end Asset Build
    run_command("npm run build", "Compiling Production Vite Assets")

    # 2. Run PHP Artisan Tests
    run_command("php artisan test", "Executing Automated PHP Unit Tests")

    # 3. Optimize Artisan Cache
    run_command("php artisan config:cache", "Caching Framework Configurations")
    run_command("php artisan route:cache", "Caching Route Table")
    run_command("php artisan view:cache", "Caching Compiled Views")

    print("==========================================================")
    print("SUCCESS: Saba POS System is 200% Production Ready for Live Launch!")
    print("==========================================================")

if __name__ == "__main__":
    main()
