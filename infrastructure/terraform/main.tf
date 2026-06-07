########################################################
# Aegis Filter – Terraform Azure Provisioning
# Provisiona: Resource Group, VNet, Subnet, IP Pública,
# NSG (puertos 22, 80, 443), NIC y VM Standard_B1s Debian 12
########################################################

terraform {
  required_version = ">= 1.6.0"
  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 3.100"
    }
  }
}

provider "azurerm" {
  features {}
}

# ─── Variables ────────────────────────────────────────
variable "location" {
  description = "Región de Azure"
  default     = "East US"
}

variable "prefix" {
  description = "Prefijo para nombres de recursos"
  default     = "aegisfilter"
}

variable "admin_username" {
  description = "Usuario admin de la VM"
  default     = "azureuser"
}

variable "admin_password" {
  description = "Contraseña admin (usar variable de entorno TF_VAR_admin_password)"
  sensitive   = true
}

# ─── Resource Group ───────────────────────────────────
resource "azurerm_resource_group" "rg" {
  name     = "${var.prefix}-rg"
  location = var.location

  tags = {
    Project     = "AegisFilter"
    Environment = "Production"
    Course      = "SI784-CalidadPruebas"
  }
}

# ─── Virtual Network ──────────────────────────────────
resource "azurerm_virtual_network" "vnet" {
  name                = "${var.prefix}-vnet"
  address_space       = ["10.0.0.0/16"]
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
}

resource "azurerm_subnet" "subnet" {
  name                 = "${var.prefix}-subnet"
  resource_group_name  = azurerm_resource_group.rg.name
  virtual_network_name = azurerm_virtual_network.vnet.name
  address_prefixes     = ["10.0.1.0/24"]
}

# ─── IP Pública ───────────────────────────────────────
resource "azurerm_public_ip" "public_ip" {
  name                = "${var.prefix}-pip"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name
  allocation_method   = "Static"
  sku                 = "Standard"
}

# ─── Network Security Group ───────────────────────────
resource "azurerm_network_security_group" "nsg" {
  name                = "${var.prefix}-nsg"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name

  security_rule {
    name                       = "Allow-SSH"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "22"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  security_rule {
    name                       = "Allow-HTTP"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "80"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  security_rule {
    name                       = "Allow-HTTPS"
    priority                   = 1003
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "443"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }
}

# ─── Network Interface ────────────────────────────────
resource "azurerm_network_interface" "nic" {
  name                = "${var.prefix}-nic"
  location            = azurerm_resource_group.rg.location
  resource_group_name = azurerm_resource_group.rg.name

  ip_configuration {
    name                          = "internal"
    subnet_id                     = azurerm_subnet.subnet.id
    private_ip_address_allocation = "Dynamic"
    public_ip_address_id          = azurerm_public_ip.public_ip.id
  }
}

resource "azurerm_network_interface_security_group_association" "nic_nsg" {
  network_interface_id      = azurerm_network_interface.nic.id
  network_security_group_id = azurerm_network_security_group.nsg.id
}

# ─── Virtual Machine ──────────────────────────────────
resource "azurerm_linux_virtual_machine" "vm" {
  name                = "${var.prefix}-vm"
  resource_group_name = azurerm_resource_group.rg.name
  location            = azurerm_resource_group.rg.location
  size                = "Standard_B1s"
  admin_username      = var.admin_username
  admin_password      = var.admin_password

  # Deshabilitar SSH por clave para simplificar (usar contraseña)
  disable_password_authentication = false

  network_interface_ids = [azurerm_network_interface.nic.id]

  os_disk {
    caching              = "ReadWrite"
    storage_account_type = "Standard_LRS"
    disk_size_gb         = 30
  }

  # Debian 12 (Bookworm)
  source_image_reference {
    publisher = "Debian"
    offer     = "debian-12"
    sku       = "12"
    version   = "latest"
  }

  # Script de inicialización: instala Docker y Docker Compose
  custom_data = base64encode(<<-EOF
    #!/bin/bash
    apt-get update -y
    apt-get install -y \
        ca-certificates curl gnupg lsb-release

    # Docker GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/debian/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=$(dpkg --print-architecture) \
      signed-by=/etc/apt/keyrings/docker.gpg] \
      https://download.docker.com/linux/debian \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
      | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -y
    apt-get install -y docker-ce docker-ce-cli containerd.io \
        docker-buildx-plugin docker-compose-plugin

    # Añadir usuario al grupo docker
    usermod -aG docker ${var.admin_username}
    systemctl enable docker
    systemctl start docker
  EOF
  )

  tags = {
    Project = "AegisFilter"
    Course  = "SI784-CalidadPruebas"
  }
}

#
# ─── Outputs ──────────────────────────────────────────
output "public_ip_address" {
  description = "IP pública de la VM"
  value       = azurerm_public_ip.public_ip.ip_address
}

output "ssh_connection" {
  description = "Comando SSH para conectarse"
  value       = "ssh ${var.admin_username}@${azurerm_public_ip.public_ip.ip_address}"
}

output "vm_name" {
  value = azurerm_linux_virtual_machine.vm.name
}
