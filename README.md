# PUQ_WHMCS-Mikrotik-VPN

Module for the WHMCS system.
For manage Mikrotik secrets users as a product VPN.

Functions:

- Auto create and deploy produkt VPN
- Only Mikrotik API using
- Multilanguage

Admin area:

- Create users
- Suspend users
- Terminate users
- Unsuspend users
- Change the VPN users password
- Change Package
- VPN connection status
- Reset Connection

Client area:

- Change the VPN password
- VPN connection status

---------------------------------------------------------------
Testing:

WHMCS: 8.1.3

Mikrotik: CHR 7.3.1

--------------------------------------------------------------
### WHMCS part setup guide
1. ```git clone https://github.com/PUQ-sp-z-o-o/PUQ_WHMCS-Mikrotik-VPN.git```
2. Copy "puqMikrotikVPN" to "WHMCS_WEB_DIR/modules/servers/"

2. Create new server Mikrotik in WHMCS (System Settings->Products/Services->Servers)  
- Hostname: Mikrotik DNS (vpn.xxxxx.xxx)
- Module: PUQ Mikrotik VPN
- Assigned IP Addresses: pool of IP address for VPN users (One per line)	
- Username: Mikrotik admin user
- Password: Mikrotik admin user password
- Port 443 (not 8729)


3. Create a new Products/Services
- Module Settings/Module Name: PUQ Mikrotik VPN

### Mikrotik part setup guide
Enabling HTTPS
Create your own root CA on your router
```
/certificate
add name=LocalCA common-name=LocalCA key-usage=key-cert-sign,crl-sign
```
Sign the newly created CA certificate
```
/certificate
sign LocalCA
```
Create a new certificate for Webfig (non-root certificate)
```
/certificate
add name=Webfig common-name=XXX.XXX.XXX.XXX
```
Sign the newly created certificate for Webfig
```
/certificate
sign Webfig ca=LocalCA 
```
Enable www-ssl and specify to use the newly created certificate for Webfig
```
/ip service
set www-ssl certificate=Webfig disabled=no
```
Enable api-ssl and specify to use the newly created certificate for Webfig
```
 /ip service 
 set api-ssl certificate=Webfig disabled=no 
```
