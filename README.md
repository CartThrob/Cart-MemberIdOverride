extension-member-id-override
============================

This extension allows a superadmin to create a transaction for a customer by assigning a member id in the checkout form. 

Add an input called "checkout_as_customer_id" with the member id you want to assign the purchase to. 
Set the checkout_as_customer_id parameter to "yes" as well

	{exp:cartthrob:checkout_form return="" checkout_as_customer_id="yes" }

		{gateway_fields}

		<input type="submit" value="Checkout" />
		<input type="hidden" value="123" name="checkout_as_customer_id" /> 
	{/exp:cartthrob:checkout_form}


This add-on is provided as-is at no cost with no warranty expressed or implied. Support is not included. 