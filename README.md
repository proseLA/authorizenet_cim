# authorizenet_cim
CIM Module for authorizenet for zencart
this is an update for the authorizenet_cim module done years ago for zencart.  the biggest difference here is its is NOT for reordering products!  it is for keeping a credit card on file for the customer to use for future purchases.

currently, it integrates with the super order/edit orders module (i don't know which), but authorization is done on the admin side.  the customer side only creates a paymentProfileID which is a tokenized version of the credit card.  the actual authorization and capture happens on the admin side, although as this evolves i'm sure we can configure it differently.
