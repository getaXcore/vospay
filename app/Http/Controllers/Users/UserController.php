<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 07/12/2018
 * Time: 16:43
 */

namespace App\Http\Controllers\Users;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class UserController extends Controller
{
    public $statusCode = array();
    private $codeOK;
    private $codeFAIL;
    private $codeARACTIVATED;
    private $codeDDNMATCH;
    private $codeBIRTHDATE;
    private $codeNOTRX;
    private $codeIDCARDNUM;
    private $codePHONENUM;

    public function __construct()
    {
        $this->codeOK = $this->statusCode[0] = array("statusCode"=>"88","description"=>"OK");
        $this->codeFAIL = $this->statusCode[1] = array("statusCode"=>"00","description"=>"FAIL");
        $this->codeARACTIVATED = $this->statusCode[2] = array("statusCode"=>"01","description"=>"Account Already Activated");
        $this->codeDDNMATCH = $this->statusCode[3] = array("statusCode"=>"02","description"=>"Data Do Not Match");
        $this->codeBIRTHDATE = $this->statusCode[4] = array("statusCode"=>"15","description"=>"Date of Birth Do Not Match");
        $this->codeIDCARDNUM = $this->statusCode[7] = array("statusCode"=>"12","description"=>"ID Card Number Do Not Match");
        $this->codePHONENUM = $this->statusCode[8] = array("statusCode"=>"11","description"=>"Mobile Phone Number Do Not Match");
        $this->codeNOTRX = $this->statusCode[9] = array("statusCode"=>"13","description"=>"No Transaction");
    }

    public function preActUser(Request $request){
        $param = json_decode($request->getContent(),true);

        $mPhone = trim($param['mobilePhone']);
        $idCNumber = trim($param['idCardNumber']);
        //$fName = strtolower(trim($param['fullName']));
        //$email = strtolower(trim($param['emailAddress']));
        $dBirth = trim($param['dateofBirth']); //YYYYMMDD

        $user = DB::table('omf_vospay_user')
            ->select('omf_vospay_user.*', 'omf_vospay_balance.balance_limit')
            ->leftJoin('omf_vospay_balance', 'omf_vospay_user.user_mobile_number', '=', 'omf_vospay_balance.balance_mobile_number')
            ->where('omf_vospay_user.user_mobile_number',$mPhone)
            ->first();

		//get isEmployee true or false
		$isEmployee = "false";
		if($user->user_type == "3"){
			$isEmployee = "true";
		}
		//---------------------------

        if (empty($user) || empty($mPhone)){
            $data = $this->codePHONENUM;
        }else{
            if ($dBirth != trim($user->user_birthdate)){
                $data = $this->codeBIRTHDATE;
            }elseif ($idCNumber != trim($user->user_idcard_number) ){
                $data = $this->codeIDCARDNUM;
            }/*elseif ($fName != strtolower(trim($user->user_fullname))){
                $data = $this->codeFULLNAME;
            }elseif ($email != strtolower(trim($user->user_email))){
                $data = $this->codeEMAIL;
            }*/elseif ($user->user_active == 1){
                $data = $this->codeARACTIVATED;
            }else{
                $content = array("customerName"=>$user->user_fullname,"emailAddress"=>$user->user_email,"creditLimit"=>$user->balance_limit,"isEmployee" => $isEmployee,"customerType"=>$user->user_type);
                $data = array_merge($this->codeOK,$content);
            }
        }

        return response($data,200);

    }

    public function ActivationUser(Request $request){
        $param = json_decode($request->getContent(),true);

        $mPhone = trim($param['mobilePhone']);
        $idCNumber = trim($param['idCardNumber']);
        //$fName = strtolower(trim($param['fullName']));
        //$email = strtolower(trim($param['emailAddress']));
        $dBirth = trim($param['dateofBirth']); //YYYYMMDD

        $user = DB::table('omf_vospay_user')
            ->select('omf_vospay_user.*', 'omf_vospay_balance.balance_limit')
            ->leftJoin('omf_vospay_balance', 'omf_vospay_user.user_mobile_number', '=', 'omf_vospay_balance.balance_mobile_number')
            ->where('omf_vospay_user.user_mobile_number',$mPhone)
            ->first();

        if (empty($user) || empty($mPhone)){
            $data = $this->codePHONENUM;
        }else{
            if ($idCNumber != trim($user->user_idcard_number)){
                $data = $this->codeIDCARDNUM;
            }elseif ($dBirth != trim($user->user_birthdate)){
                $data = $this->codeBIRTHDATE;
            }elseif ($user->user_active == 1){
                $data = $this->codeARACTIVATED;
            }else{
                //Update Activation value to be 1
                DB::table('omf_vospay_user')
                    ->where('user_mobile_number', trim($user->user_mobile_number))
                    ->update(['user_active' => 1]);

                $content = array("customerName"=>$user->user_fullname,"emailAddress"=>$user->user_email,"creditLimit"=>$user->balance_limit,"customerType"=>$user->user_type);
                $data = array_merge($this->codeOK,$content);
            }
        }

        return response($data,200);
    }

    public function DetailUser(Request $request){
        $param = json_decode($request->getContent(),true);

        $mPhone = $param['phoneNumber'];

        $user = DB::table('omf_vospay_user')
            ->select('user_fullname','user_email','user_fulladdress','user_birthdate','user_profession')
            ->where('user_mobile_number',$mPhone)
            ->first();

        if (empty($user)){
            $data = $this->codeDDNMATCH;
        }else{
            $result = array(
                "fullName" => $user->user_fullname,
                "emailAddress" => $user->user_email,
                "mailingAddress" => $user->user_fulladdress,
                "dateofBirth" => $user->user_birthdate,
				"profession" => $user->user_profession
            );
            $data = array_merge($this->codeOK,$result);
        }

        return response($data,200);
    }

    public function ActivityUser(Request $request){
        $param = json_decode($request->getContent(),true);

        $mPhone = $param['phoneNumber'];

        $user = DB::table('omf_vospay_transaction as a')
            ->select("c.contract_number","b.installment_each_amount","b.installment_interest","a.transaction_order_id","a.transaction_created","a.transaction_total_amount","d.payment_owed_remaining","d.payment_terms","d.payment_terms_remaining","d.payment_next_date","d.payment_next_amount","d.payment_last_date","d.payment_last_amount")
            ->leftJoin('omf_vospay_installment as b', 'a.transaction_id', '=', 'b.installment_transaction_id')
            ->leftJoin('omf_vospay_contract as c','b.installment_id','=','c.contract_installment_id')
            ->leftJoin('omf_vospay_payment as d','c.contract_number','=','d.payment_contract_number')
            ->where('a.transaction_mobile_number',$mPhone)
            ->where('a.transaction_flag',1)
            ->where('b.installment_flag',1)
            ->get();

        $balance = DB::table('omf_vospay_balance')
            ->select('balance_limit','balance_available')
            ->where('balance_mobile_number',$mPhone)
            ->first();

        if (empty($user)){

            if (empty($balance)){
                $data = $this->codeDDNMATCH;
            }else{
                $result = array(
                    "creditLimit"=>$balance->balance_limit,
                    "availableCredit"=>$balance->balance_available,
                    "contract"=>array()
                );

                $data = array_merge($this->codeOK,$result);
            }

        }else{

            $contract = array();
			$installmentAmount = array();

            foreach ($user as $value){
                $contract[] = array(
                    "contractNumber" => $value->contract_number,
                    "orderID" => $value->transaction_order_id,
                    "transactionDate" => str_replace('-','',substr($value->transaction_created,0,10)),
                    "transactionAmount" => $value->transaction_total_amount,
                    "remainingOwed" => $value->payment_owed_remaining,
                    "interestRate" => $value->installment_interest,
                    "numberofTerms" => $value->payment_terms,
                    "termsRemaining" => $value->payment_terms_remaining,
                    "nextDueDate" => $value->payment_next_date,
                    "nextDueAmount" => $value->payment_next_amount,
                    "paymentHistory" => array(
                        "lastPaymentDate" => $value->payment_last_date,
                        "lastPaymentAmount" => $value->payment_last_amount
                    )
                );
				
				$installmentAmount[] = $value->installment_each_amount; 
            }
			
			$totalAmountDue = array_sum($installmentAmount);

            $result = array(
                "creditLimit"=>$balance->balance_limit,
                "availableCredit"=>$balance->balance_available,
                "contract"=>$contract,
				"totalAmountDue"=>$totalAmountDue
            );

            $data = array_merge($this->codeOK,$result);
        }
		
		//print_r($installmentAmount);
        return response($data,200);
    }

    public function BaseController(){
        print_r(URL::to('/'));
    }

}