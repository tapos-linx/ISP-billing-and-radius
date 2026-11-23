# Carrier Multi-Region Infrastructure as Code (Terraform)

## Overview
This directory contains production Terraform configurations for provisioning multi-region Points of Presence (POPs) across the carrier network footprint in Bangladesh (Dhaka Core, Chittagong, Sylhet, and Khulna).

---

## Files

| File | Description |
| :--- | :--- |
| `main.tf` | Provider initialization, VPC definitions, subnets, and security groups. |
| `multi_region_pop.tf` | Regional POP definitions, BGP Anycast IP allocation, compute node provisioning, and cross-region interconnects. |
| `variables.tf` | Parameter definitions (regions, CIDR blocks, node sizes, high availability toggles). |
| `outputs.tf` | Exported endpoints, Anycast VIPs, internal database cluster IPs, and gateway IPs. |

---

## Deployment Workflow

### 1. Initialize Providers
```bash
cd deploy/terraform
terraform init
```

### 2. Validate & Plan
```bash
terraform validate
terraform plan -out=tfplan
```

### 3. Apply Plan
```bash
terraform apply tfplan
```
