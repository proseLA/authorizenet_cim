# authorizenet_cim
CIM Module for authorizenet for zencart. 
this is a new version of the authorizenet.net API module.  currently, you MUST have CIM enabled on your authorize.net account for this module to work.  it is for keeping a credit card on file for the customer to use for future purchases.  the credit card is tokenized and stored on authorize.net servers.

the code repurposes some code from the super orders ZC module which does not seem to be active.  it is NOT meant as a replacement for that module.  it may play nice with super orders.  i do not know, i have not tested.  i have modified all of the namespaces, so that hopefully they can place nice together.

to use this module, you must subscribe to the authorize.net CIM program.  the last time i checked, this added another $10/month to your authorize.net bill.

currently, the module does NOT modify any ZC core files.

refunds must be issued within 120 days of the transaction.  this is inherent in the system from authorize.net.

the code was developed for ZC v156 running php 7.3.  there is no implication that it will run with earlier versions of ZC, and while anyone is free to do as they wish, no support will be provided by me for earlier versions of ZC.

it has also been tested to run with v157-alpha without problem.  it also tested fine with the popular OPC plug-in.

# quick note on PCI-DSS
PCI-DSS is legal document.  i am not a lawyer.  i offer no opinion on this module as to how it effects your PCI-DSS status.  

i have done everything that i think necessary to protect cardholder data.  if you think this module violates any tenet of PCI-DSS, feel free to open an issue on github.  this code is at:  https://github.com/proseLA/authorizenet_cim/  the code is there for anyone to see, and i take protection of cardholder data seriously.

# debugging
currently this module will log all FAILED transactions to cim_response.log in the logs directory.  to see all transactions in that log file, one can set the key MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING to true; OR create a new constant called DEBUG_CIM and set that to true.  both of these do the same thing.  they will log all transaction responses to the cim_response.log; as well as make use of the ZC logger to create a ZC type log.

i am working on changing the prefix of those ZC logs to be cimDEBUG as opposed to myDEBUG but am currently having a little difficulty with that.

# notes on new changes / not previously documented
card_update is available you need to integrate it with your template.  it works fine with responsive_classic but i am not a styling guy, and making it look pretty with every template it not my thing.  to make it easily accessible, one would add a link on tpl_account_default.php
separated a void window from a refund window based on transaction status

