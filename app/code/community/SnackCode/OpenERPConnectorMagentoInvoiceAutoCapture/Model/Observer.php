<?php
/*Written by Dylan Frankland - dylan.frankland+SnackCode@gmail.com*/
class SnackCode_OpenERPConnectorMagentoInvoiceAutoCapture_Model_Observer
{
    /*email function to alert of failed captures*/
    protected function sendEmail($subject,$body)
    {
        $mail = Mage::getModel('core/email');
        $mail->setToName(Mage::getStoreConfig('trans_email/ident_support/name'));
        $mail->setToEmail(Mage::getStoreConfig('trans_email/ident_support/email'));
        $mail->setFromEmail(Mage::getStoreConfig('trans_email/ident_support/email'));
        $mail->setFromName(Mage::app()->getStore()->getName());
        $mail->setBody($body);
        $mail->setSubject($subject);
        $mail->setType('html');// You can use 'html' or 'text'
        try
        {
            $mail->send();
        }
        catch (Exception $e)
        {
            Mage::logException('OpenERP Connector - Magento: Invoice AutoCapture // Exception message: '.$e->getMessage());
        }
    }
    
    /*save everything after you have altered it in any way*/
    protected function saveEverything($order,$payment,$invoice)
    {
        Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($payment)->addObject($order)->save();
    }
    
    /*log every success or issue using email, mage log at /var/log/system.log, and also the order comments*/
    protected function logEverything($subject,$body,$oid,$order)
    {
        $oid = 'Order #'.$oid.' ';
        $this->sendEmail($oid.$subject,$body);
        Mage::log($oid.$subject,Zend_Log::DEBUG,'OpenERPConnectorMagentoInvoiceAutoCapture.log',true);
        $order->addStatusHistoryComment($subject.$body, true);
        $this->saveEverything($order,$payment,$invoice);
    }
    
    public function invoiceAutoCapture($observer)
    {
        /*get all order/invoice/payment details from the observer*/
        $event = $observer->getEvent();
        $invoice = $event->getInvoice();
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        $orderId = $invoice->getOrder()->getIncrementId();
        
        /*Try to capture paypal payment from order invoice and email problems*/
        if($payment->canCapture() && !$order->canInvoice())
        {
            $success = true;
            try
            {
                $invoice->capture();
            }
            catch (Exception $e)
            {
                $success = false;
                $subject = 'OpenERP Connector - Magento: Invoice AutoCapture // Exception Message';
                $body = $e->getMessage();
                $this->logEverything($subject,$body,$orderId,$order);
            }
            if($success)
            {
                $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
                $order->setIsInProcess(true);
                $order->addStatusHistoryComment('OpenERP Connector - Magento: Invoice AutoCapture // Success!', true);
                $invoice->sendEmail();
                $this->saveEverything($order,$payment,$invoice);
            }
        }
        else
        {
            $subject = 'OpenERP Connector - Magento: Invoice AutoCapture // Cannot Capture Paypal Payment!';
            $body = 'Authorization failed, or the invoice is not yet created. $invoice->canCapture()=false.';
            $this->logEverything($subject,$body,$orderId,$order);
        }
        return $this;
    }
}
?>