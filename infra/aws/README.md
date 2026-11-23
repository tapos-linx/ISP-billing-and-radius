# AWS Staging Deployment Runbook (Stage 11)

## Overview
This directory contains the automation scripts for deploying and verifying the carrier stack on **AWS Free Tier (t3.micro / t4g.small)** with zero-cost billing isolation and fail-closed safety guards.

---

## Files

| File | Purpose |
| :--- | :--- |
| `deploy_aws_free_tier.ps1` | Orchestrates EC2 instance creation, VPC/Security Group provisioning, and Docker stack deployment on AWS. |
| `ec2_bootstrap.sh` | Cloud-init shell script that updates packages, installs Docker/Compose, clones repo, pulls images, and boots services. |
| `verify_aws_staging.ps1` | Verification script validating live HTTP 200 health responses, RADIUS Access-Accept, and automated resource teardown. |

---

## Safety Guards & Invariants
- **Billing Cap Enforced**: Automatically terminates staging instances if usage threatens to exceed the AWS free tier limits.
- **Port Isolation**: Access to SSH (22), RADIUS (1812/1813), and CoA (3799) is restricted strictly to operator IP addresses via AWS Security Groups.
- **Automated Teardown**: Includes immediate cleanup triggers to avoid persistent resource charges.
