# authorizenet_cim
CIM Module for authorizenet for zencart
this is an update for the authorizenet_cim module done years ago for zencart.  the biggest difference is this module is NOT for reordering products.  it is for keeping a credit card on file for the customer to use for future purchases.  the credit card is tokenized and stored on authorize.net servers.

the code repurposes a bunch of code from the super orders ZC module which does not seem to be active.  it is NOT meant as a replacement for that module.  it may play nice with super orders.  i do not know, i have not tested.  i have modified all of the namespaces, so that hopefully they can place nice together.

to use this module, you must subscribe to the authorize.net CIM program.  the last time i checked, this added another $10/month to your authorize.net bill.

currently, the module does NOT modify any ZC core files.

the code was developed for ZC v156 running php 7.3.  there is no implication that it will run with earlier versions of ZC, and while anyone is free to do as they wish, no support will be provided for earlier versions of ZC.

# quick note on PCI-DSS
PCI-DSS is legal document.  i am not a lawyer.  i offer no opinion on this module as to how it effects your PCI-DSS status.  

i have done everything that i think necessary to protect cardholder data.  if you think this module violates any tenet of PCI-DSS, feel free to open an issue on github.  this code is at:  https://github.com/proseLA/authorizenet_cim/  the code is there for anyone to see, and i take protection of cardholder data seriously.


