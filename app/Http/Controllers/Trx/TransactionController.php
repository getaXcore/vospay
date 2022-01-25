<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 10/12/2018
 * Time: 14:48
 */

namespace App\Http\Controllers\Trx;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public $statusCode = array();
    private $codeOK;
    private $codeFAIL;
    private $codeARACTIVATED;
    private $codeDDNMATCH;
    private $codeERRLIMIT;
    private $codeERRBLOCK;
    private $codeERRMINTRX;
    private $codeINVALID;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->codeOK = $this->statusCode[0] = array("statusCode"=>"88","description"=>"OK");
        $this->codeFAIL = $this->statusCode[1] = array("statusCode"=>"00","description"=>"FAIL");
        $this->codeARACTIVATED = $this->statusCode[2] = array("statusCode"=>"08","description"=>"Akun belum aktif");
        $this->codeDDNMATCH = $this->statusCode[3] = array("statusCode"=>"02","description"=>"Data Do Not Match");
        $this->codeERRLIMIT = $this->statusCode[4] = array("statusCode"=>"03","description"=>"Insufficient Limit");
        $this->codeERRBLOCK = $this->statusCode[5] = array("statusCode"=>"04","description"=>"Account Blocked");
        $this->codeERRMINTRX = $this->statusCode[6] = array("statusCode"=>"06","description"=>"Nominal transaksi yang Anda lakukan di bawah ketentuan minimum nominal transaksi");
        $this->codeINVALID = $this->statusCode[7] = array("statusCode"=>"09","description"=>"Invalid authorization ID");
    }
	
	private function getEligibleTenor($tenorDefault,$sisaTenor){
		if (!empty($sisaTenor) || $sisaTenor != 0) {
			$avaTenor = array();
			for ($i=0; $i < count($tenorDefault); $i++) { 
				if ($sisaTenor >= $tenorDefault[$i]) {
					$avaTenor[] = $tenorDefault[$i];
				}
			}
		}else{
			$avaTenor = $tenorDefault;
		}

		return $avaTenor;
	}
	private function getDateOf($tglJthTemp,$tglTrx,$tenor){
        $splitTglTrx = substr($tglTrx,8,2);
        $splitMonthTrx = substr($tglTrx,5,2);
        $splitYearTrx = substr($tglTrx,0,4);

        if($splitTglTrx > $tglJthTemp){
            $splitMonthTrx = $splitMonthTrx+1;
            if(strlen($splitMonthTrx) == 1){
                $splitMonthTrx = '0'.$splitMonthTrx;
            }
        }else{
            $splitMonthTrx = $splitMonthTrx;
        }

        $live = $splitYearTrx.'-'.$splitMonthTrx.'-'.$tglJthTemp;
        $angsuranPertama = date('Ymd', strtotime('+1 month', strtotime($live)));
        $angsuranTerkahir = date('Ymd', strtotime('+'.($tenor-1).' month', strtotime($angsuranPertama)));

        $result = array(
            "TanggalJatuhTempo "=>$tglJthTemp,
            "TanggalTransaksi "=>$tglTrx,
            "Tenor"=>$tenor,
            "Live"=>$live,
            "AngsuranPertama"=>$angsuranPertama,
            "AngsuranTerakhir"=>$angsuranTerkahir
        );

        return $result;
    }
	private function maxIdNumber($maxId){
		return sprintf("9%02s",$maxId);
	} 
	
    public function authorizeTrans(Request $request)
    {
        $param = json_decode($request->getContent(),true);

        $mPhone = trim($param['mobilePhone']);
        $orderId = trim($param['orderID']);
        $merchantId = trim($param['merchantID']);
        $merchantName = trim($param['merchantName']);
        $shippingFee = trim($param['shippingFee']);
        $insuranceFee = trim($param['insuranceFee']);
        $additionalFee = trim($param['additionalFee']);
        $discount = trim($param['discount']);
        $otherFee = trim($param['otherFee']);
        $totalPurchaseAmount = trim($param['TotalPurchaseAmount']);

        /*$trans = DB::table('omf_vospay_transaction')
            ->select(DB::raw('count(*) as transaction_count,transaction_id'))
            ->where('transaction_order_id',$orderId)
            ->where('transaction_flag',1)
            ->first();*/

        $user = DB::table('omf_vospay_user')
            ->select('user_blocked','user_active','user_type','user_birthdate','nktr','MaxTenor','FlatRate','RealAngsuran','PeriodeAngsuran','ApprovedBy','user_fulladdress','user_profession','TotalHutang')
            ->where('omf_vospay_user.user_mobile_number',$mPhone)
            ->first();

        if (empty($user)){
            $data = $this->codeDDNMATCH;
        }elseif ($user->user_blocked == 1){
            $data = $this->codeERRBLOCK;
        }elseif ($user->user_active == 0){
            $data = $this->codeARACTIVATED;
        }
        /*elseif ($trans->transaction_count > 0){

            //For Edit Transaction

            $transactionId = $trans->transaction_id;

            //delete all transaction item
            DB::table('omf_vospay_transaction_item')->where('item_transaction_id', '=',$transactionId)->delete();

            //insert new item
            for ($i=0;$i<count($param['purchasedItems']);$i++){

                //save item transaction
                DB::table('omf_vospay_transaction_item')->insert(
                    [
                        'item_transaction_id' => $transactionId,
                        'item_product_id' => $param['purchasedItems'][$i]['ProductID'],
                        'item_product_name' => $param['purchasedItems'][$i]['ProductName'],
                        'item_product_price' => $param['purchasedItems'][$i]['ProductPrice'],
                        'item_product_quantity' => $param['purchasedItems'][$i]['ProductQuantity'],
                        'item_refund_status' => 0
                    ]
                );

            }

            //update transaction
            $detailUpdateTrans = array(
                "transaction_merchant_id"=>$merchantId,
                "transaction_merchant_name"=>"$merchantName",
                "transaction_shipping_fee"=>$shippingFee,
                "transaction_insurance_fee"=>$insuranceFee,
                "transaction_additional_fee"=>$additionalFee,
                "transaction_discount"=>$discount,
                "transaction_tax_other"=>$otherFee,
                "transaction_total_amount"=>$totalPurchaseAmount,
                "transaction_updated"=>date('Y-m-d H:i:s')
            );
            DB::table('omf_vospay_transaction')
                ->where('transaction_id', $transactionId)
                ->where('transaction_flag',1)
                ->update($detailUpdateTrans);

            //update installment
            $install = DB::table('omf_vospay_installment')
                ->select('installment_interest','installment_lenght_month')
                ->where('installment_transaction_id',$transactionId)
                ->where('installment_flag',1)
                ->first();

            $tenor = $install->installment_lenght_month;
            $rate = $install->installment_interest;

            $installmentRate = intval($rate)*intval($totalPurchaseAmount);
            $installment = round((intval($totalPurchaseAmount)+intval($installmentRate))/$tenor);

            $detailUpdateInst = array("installment_each_amount"=>$installment,"installment_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_installment')
                ->where('installment_transaction_id', $transactionId)
                ->where('installment_flag',1)
                ->update($detailUpdateInst);

            $data = $this->codeOK;

        }*/
        else{
			
            $avaLimit = DB::table('omf_vospay_balance')
                ->select('balance_limit','balance_available')
                ->where('balance_mobile_number',$mPhone)
                ->first();

            $minTransAmount = DB::table('omf_vospay_config')
                ->select('omf_vospay_config.config_value')
                ->where('omf_vospay_config.config_name','min_transaction_amount')
                ->first();

            if ($totalPurchaseAmount > $avaLimit->balance_available){
                $data = $this->codeERRLIMIT;
            }elseif ($totalPurchaseAmount < $minTransAmount->config_value){
                $data = $this->codeERRMINTRX;
            }else{
				//get isEmployee true or false
				$isEmployee = "false";
				if($user->user_type == "3"){
					$isEmployee = "true";
				}
				//---------------------------

                //save transaction
                DB::table('omf_vospay_transaction')->insert(
                    [
                        'transaction_mobile_number' => $mPhone,
                        'transaction_order_id' => $orderId,
                        'transaction_merchant_id' => $merchantId,
                        'transaction_merchant_name' => $merchantName,
                        'transaction_shipping_fee' => $shippingFee,
                        'transaction_insurance_fee' => $insuranceFee,
                        'transaction_additional_fee' => $additionalFee,
                        'transaction_discount' => $discount,
                        'transaction_tax_other' => $otherFee,
                        'transaction_total_amount' => $totalPurchaseAmount,
                        'transaction_flag' => 0,
                        'transaction_created' => date('Y-m-d H:i:s')
                    ]
                );

                $transactionId = DB::getPdo()->lastInsertId();

                for ($i=0;$i<count($param['purchasedItems']);$i++){

                    //save item transaction
                    DB::table('omf_vospay_transaction_item')->insert(
                        [
                            'item_transaction_id' => $transactionId,
                            'item_product_id' => $param['purchasedItems'][$i]['ProductID'],
                            'item_product_name' => $param['purchasedItems'][$i]['ProductName'],
                            'item_product_price' => $param['purchasedItems'][$i]['ProductPrice'],
                            'item_product_quantity' => $param['purchasedItems'][$i]['ProductQuantity'],
                            'item_refund_status' => 0
                        ]
                    );

                }
				
				if($user->user_type == 4)
				{
					$tenor = $user->MaxTenor;
					$rate = $user->FlatRate;
					$adm = 0;
					$installment = $user->RealAngsuran;

					DB::table('omf_vospay_installment')->insert(
							 [
								  'installment_transaction_id' => $transactionId,
								  'installment_lenght_month' => $tenor,
								  'installment_interest' => $rate,
								  'installment_adm_interest' => $adm,
								  'installment_each_amount' => $installment,
								  'installment_flag' => 0,
								  'installment_created' => date('Y-m-d H:i:s')
							 ]
						 );
				}else{

					//hitung installment
					$rates = DB::table('omf_vospay_tenorate')
						->select('tenorate_month','tenorate_value','tenorate_adm_value')
						->where('tenorate_type',$user->user_type)
						->get();

					foreach ($rates as $value){

						 $tenor = $value->tenorate_month;
						 $rate = $value->tenorate_value;
						 $adm = $value->tenorate_adm_value;

						 if($user->user_type == 3){
							 $admRate = $adm*intval($totalPurchaseAmount);
							 $bungaTotal = (intval($totalPurchaseAmount)+$admRate)*$rate*$tenor;
							 $nInstallment = round((intval($totalPurchaseAmount)+$bungaTotal+$admRate)/$tenor);
						 }else{
							 $installmentRate = ($rate*intval($totalPurchaseAmount))*$tenor;
							 $nInstallment = round((intval($totalPurchaseAmount)+intval($installmentRate))/$tenor);
						 }

						  
						 

						 if(substr($nInstallment,-3) > 499){
							 $installment = round($nInstallment,-3);
						 }else{
							 $installment = round($nInstallment,-3)+1000;
						 }
						  

						 DB::table('omf_vospay_installment')->insert(
							 [
								  'installment_transaction_id' => $transactionId,
								  'installment_lenght_month' => $tenor,
								  'installment_interest' => $rate,
								  'installment_adm_interest' => $adm,
								  'installment_each_amount' => $installment,
								  'installment_flag' => 0,
								  'installment_created' => date('Y-m-d H:i:s')
							 ]
						 );

					}
				}

                

                $arrAmount = str_split($totalPurchaseAmount);
                 if (count($arrAmount) == 6 && $arrAmount[0] == 1){ // jika seratus hingga empat ratus ribuan
                    $installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month',1)
                        ->get();
                }elseif(count($arrAmount) == 6 && $arrAmount[0] == 2){
					$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month',1)
                        ->get();
				}elseif(count($arrAmount) == 6 && $arrAmount[0] == 3){
					$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month',1)
                        ->get();
				}elseif(count($arrAmount) == 6 && $arrAmount[0] == 4){
					$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month',1)
                        ->get();
				}elseif($user->user_type < 3){ //tenornya tidak melebihi max tenor user dan jika bukan karyawan
                    $installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month','<',$user->MaxTenor)
						->orderBy('installment_lenght_month', 'asc')
                        //->where('installment_lenght_month','<>',1)
                        ->get();
                }elseif ($user->user_type == 3){ //jika karyawan
					if(count($arrAmount) > 6){
						$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month','<>',1)
						->orderBy('installment_lenght_month', 'asc')
                        ->get();
					}elseif(count($arrAmount) == 6 && $arrAmount[0] >= 5){
						$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month','<>',1)
						->orderBy('installment_lenght_month', 'asc')
                        ->get();
					}else{
						$installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month','=',1)
                        ->get();
					}
                }else{
                    $installmentPlan = DB::table('omf_vospay_installment')
                        ->select('installment_id','installment_lenght_month','installment_interest','installment_adm_interest','installment_each_amount')
                        ->where('installment_transaction_id',$transactionId)
                        ->where('installment_lenght_month','<>',1)
						->orderBy('installment_lenght_month', 'asc')
                        ->get();
                }

                $arrInstallmentPlan = array();

                foreach ($installmentPlan as $value){

                    $arrInstallmentPlan[] = array(
                        "planID" => $value->installment_id,
                        "interestrate" => $value->installment_interest,
						"interestadmrate" => $value->installment_adm_interest,
                        "lengthOfLoan" => $value->installment_lenght_month,
                        "amountofEachInstallment" => $value->installment_each_amount,
						"totalLoan" => $user->TotalHutang
                    );

                }
				
				$max = DB::table('omf_vospay_transaction')
					->where('transaction_mobile_number',$mPhone)
					->count();
						
						//print_r($max);
						
				if(empty($user->nktr)){
					$nktr = $mPhone;
				}else{
					$nktr = substr($user->nktr,0,10);
				}
				//tambahan
				if($user->user_type == 4){
					$contractNumber = $user->nktr;
				}else{
					//$contractNumber = date('Ym').'JTO'.$user->user_birthdate.date('is');				
					$contractNumber = $nktr."-".$this->maxIdNumber($max);
				}
                $contractDate = date('Ymd');

                DB::table('omf_vospay_contract')->insert(
                    [
                        'contract_mobile_number' => $mPhone,
                        'contract_number' => $contractNumber,
                        'contract_date' => $contractDate,
                        'contract_transaction_id' => $transactionId,
                        'contract_prospect_id' => $transactionId.date('is')
                    ]
                );

                $trans = DB::table('omf_vospay_transaction')
                    ->select('transaction_mobile_number','transaction_id')
                    ->where('transaction_mobile_number',$mPhone)
                    ->orderBy('transaction_id','desc')
                    ->first();

                $balance = DB::table('omf_vospay_balance')
                    ->select('balance_limit','balance_available')
                    ->where('balance_mobile_number',$mPhone)
                    ->first();
					
				$collateral = DB::table('omf_vospay_collateral')
					->select('collateral_Jttempo','collateral_alamat','collateral_Pekerjaan','collateral_nktr','collateral_merek_tipe','collateral_tahun','collateral_warna','collateral_norangka_snid','collateral_kondisi','collateral_Realisasidate','collateral_nomorMesin')
					->where('collateral_nktr',$user->nktr)
					->first();

				//tambahan
				$jadwalAngsuran = DB::table('omf_vospay_schedule')
					->select('schedule_nktr','schedule_cicildate','schedule_amount')
					->where('schedule_nktr',$user->nktr)
					->get();
				
				//ambil nama pasangan debitur
				$pasangandebitur = DB::table('omf_vospay_spouse')
					->select('spouse_name')
					->where('spouse_nktr',$user->nktr)
					->first();
					

				if(!empty($collateral)){
					$collateral = array(
						"brandType"=>trim($collateral->collateral_merek_tipe),
						"year"=>$collateral->collateral_tahun,
						"color"=>trim($collateral->collateral_warna),
						"rangka"=>trim($collateral->collateral_norangka_snid),
						"condition"=>trim($collateral->collateral_kondisi),
						"machine"=>trim($collateral->collateral_nomorMesin),
						"realizationNumber"=>trim($collateral->collateral_nktr),
						"realizationDate"=>str_replace('-','',substr($collateral->collateral_Realisasidate,0,10)),
						"address"=>trim($collateral->collateral_alamat),
						"profession"=>trim($collateral->collateral_Pekerjaan)
					);
				}else{
					$collateral = array(
						"brandType"=>"",
						"year"=>"",
						"color"=>"",
						"rangka"=>"",
						"condition"=>"",
						"machine"=>"",
						"realizationNumber"=>"",
						"realizationDate"=>"",
						"address"=>$user->user_fulladdress,
						"profession"=>$user->user_profession,
						"spouseName"=>$pasangandebitur->spouse_name
					);
				}
				
				//tambahan
				$jadwalAngsuranArr = array();

				if(!empty($jadwalAngsuran)){
					
					$num = 0;
					foreach ($jadwalAngsuran as $value){
						$num++;
						$jadwalAngsuranArr[] = array(
							"installmentNumber" => $num,
							"installmentCicildate" => $value->schedule_cicildate,
							"installmentAmount" => $value->schedule_amount
						);
					}

				}
				//---------------end----------------------------

				if($user->PeriodeAngsuran == 1){
					$PeriodeAngsuran = "bulanan";
				}elseif($user->PeriodeAngsuran == 8){
					$PeriodeAngsuran = "harian";
				}elseif($user->PeriodeAngsuran == 9){
					$PeriodeAngsuran = "mingguan";
				}else{
					$PeriodeAngsuran = "bulanan";
				}

                $result = array(
                    "authorizationID" => $trans->transaction_id,
                    "customerPhoneNumber" => $trans->transaction_mobile_number,
                    "creditLimit" => $balance->balance_limit,
                    "availableCredit" => $balance->balance_available,
                    "eligibleInstallmentPlans" => $arrInstallmentPlan,
                    "crossContractList" => array("contractNumber"=>$contractNumber,"contractDate"=>$contractDate),
					"collateral" => $collateral,
					"isEmployee" => $isEmployee,
					"customerType" => $user->user_type,
					"PeriodeAngsuran" => $PeriodeAngsuran,
					"ApprovedBy" => $user->ApprovedBy,
					"PeriodeType" => $user->PeriodeAngsuran, //tambahan
					"installmentSchedule" => $jadwalAngsuranArr //tambahan
                );

                $data = array_merge($this->codeOK,$result);
            }
        }

        return response($data,200);
    }

    public function finalizeTrans(Request $request){
        $param = json_decode($request->getContent(),true);

        $transactionId = trim($param['authorizationID']);
        $installmentId = trim($param['planID']);

        $trans = DB::table('omf_vospay_transaction')
            ->select('transaction_created','transaction_id','transaction_total_amount','transaction_mobile_number','transaction_flag')
            ->where('transaction_id',$transactionId)
            ->first();

        if (empty($trans)){
            $data = $this->codeINVALID;
        }elseif ($trans->transaction_flag == 1 || $trans->transaction_flag == 2){
            $data = $this->codeFAIL;
        }else{
			$userWithNik = DB::table('omf_vospay_user')
				->select('user_description')
				->where('omf_vospay_user.user_mobile_number',$trans->transaction_mobile_number)
				->where('user_type','3')
				->first();
			if(!empty($userWithNik) && !empty($userWithNik->user_description)){
				$NIK = $userWithNik->user_description;
			}else{
				$NIK = "";
			}

            //update flag transaction
            $detailUpdateTrans = array("transaction_flag"=>1,"transaction_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_transaction')
                ->where('transaction_id', $transactionId)
                ->update($detailUpdateTrans);

            //update flag installment
            $detailUpdateInst = array("installment_flag"=>1,"installment_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_installment')
                ->where('installment_id', $installmentId)
                ->update($detailUpdateInst);

            //update installment contract
            DB::table('omf_vospay_contract')
                ->where('contract_transaction_id', $transactionId)
                ->update(['contract_installment_id' => $installmentId]);

            //update balance limit and balance available
            $rate = DB::table('omf_vospay_installment')
                ->select('installment_interest','installment_lenght_month','installment_adm_interest')
                ->where('installment_id',$installmentId)
                ->first();

			
			if(!empty($rate->installment_adm_interest)){
				$admRate = $rate->installment_adm_interest * intval($trans->transaction_total_amount); //nilai asuransi
			}else{
				$admRate = "";
			} 
			
			//for microfinancing user
			$userType = DB::table('omf_vospay_user')
				->select('user_type','JatuhTempo','CicilDateAwal','CicilDateAkhir','PeriodeAngsuran','ApprovedBy','AdminFee','ProvisiFee','InsuranceFee')
				->where('omf_vospay_user.user_mobile_number',$trans->transaction_mobile_number)
				->where('user_type','4')
				->first();
			
			if(!empty($userType)){
				$transAmount = $trans->transaction_total_amount;
				$JatuhTempo = $userType->JatuhTempo;
				$CicilDateAwal = $userType->CicilDateAwal;
				$CicilDateAkhir = $userType->CicilDateAkhir;
				$ApprovedBy = $userType->ApprovedBy;
				$AdminFee = $userType->AdminFee;
				$ProvisiFee = $userType->ProvisiFee;
				$InsuranceFee = $userType->InsuranceFee;
	
				if($userType->PeriodeAngsuran == 1){
					$PeriodeAngsuran = "bulanan";
				}elseif($userType->PeriodeAngsuran == 8){
					$PeriodeAngsuran = "harian";
				}elseif($userType->PeriodeAngsuran == 9){
					$PeriodeAngsuran = "mingguan";
				}
				
			//-------------------for microfinancing user end-------------------------------------//
		
			}else{
				$installmentRate = intval($rate->installment_interest)*intval($trans->transaction_total_amount);
				$transAmount = intval($trans->transaction_total_amount)+intval($installmentRate);
				//$transAmount = intval($trans->transaction_total_amount);

				$JatuhTempo = "";
				$CicilDateAwal = "";
				$CicilDateAkhir = "";
				$PeriodeAngsuran = "bulanan";
				$ApprovedBy = "";
				$AdminFee = "";
				$ProvisiFee = "";
				$InsuranceFee = "";
			}

            

            $balance = DB::table('omf_vospay_balance')
                ->select('balance_available','balance_used')
                ->where('balance_mobile_number',$trans->transaction_mobile_number)
                ->first();

            $lastBalanceAvailable = intval($balance->balance_available)-intval($transAmount);
            $lastBalanceUsed = intval($balance->balance_used)+intval($transAmount);

            $detailUpdateBalance = array("balance_available"=>$lastBalanceAvailable,"balance_used"=>$lastBalanceUsed,"balance_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_balance')
                ->where('balance_mobile_number', $trans->transaction_mobile_number)
                ->update($detailUpdateBalance);

            //select contract number
            $contract = DB::table('omf_vospay_contract')
                ->select('contract_number')
                ->where('contract_installment_id',$installmentId)
                ->first();

            //insert contract number to payment history table
            DB::table('omf_vospay_payment')->insert(
                [
                    'payment_contract_number' => $contract->contract_number,
                ]
            );

            //select prospectId
            $getProspectId = DB::table('omf_vospay_contract')
                ->select('contract_prospect_id')
                ->where('contract_transaction_id',$transactionId)
                ->first();

            $user = DB::table('omf_vospay_user')
                ->select('nktr','user_type')
                ->where('user_mobile_number',$trans->transaction_mobile_number)
                ->first();

            $collateral = DB::table('omf_vospay_collateral')
                ->select('collateral_Jttempo')
                ->where('collateral_nktr',$user->nktr)
                ->first();

            $trxDate = substr($trans->transaction_created,0,10);
            $tenor = $rate->installment_lenght_month;

            if(!empty($collateral)){
                $jtempo = trim($collateral->collateral_Jttempo);
                $getDateOf = $this->getDateOf($jtempo,$trxDate,$tenor);
                $onInstallment = array("onInstallment"=> array("jtempo"=>trim($collateral->collateral_Jttempo),"firstDateOf"=>$getDateOf['AngsuranPertama'],"lastDateOf"=>$getDateOf['AngsuranTerakhir']));
            }else{
                $onInstallment = array("onInstallment"=> array("jtempo"=>$JatuhTempo,"firstDateOf"=>$CicilDateAwal,"lastDateOf"=>$CicilDateAkhir));
            }

			if($user->user_type == 4){
				$biayaAsuransi = $InsuranceFee;
			}else{
				$biayaAsuransi = $admRate;
			}

            $result = $this->codeOK;
            $prospectId = array("prospectID"=>$getProspectId->contract_prospect_id);
			$additional = array("nik"=>$NIK,"biayaAsuransi"=>$biayaAsuransi,"BiayaAdmin"=>$AdminFee,"BiayaProvisi"=>$ProvisiFee,"contractNumber"=>$user->nktr,"PeriodeAngsuran"=>$PeriodeAngsuran,"ApprovedBy"=>$ApprovedBy);

            $data = array_merge($result,$prospectId,$onInstallment,$additional);
        }

        return response($data,200);

    }

    public function refundTrans(Request $request){
        $param = json_decode($request->getContent(),true);

        $transactionId = trim($param['authorizationID']);

        $trans = DB::table('omf_vospay_transaction')
            ->select(DB::raw('count(*) as transaction_count,transaction_mobile_number,transaction_total_amount'))
            ->where('transaction_id',$transactionId)
            ->where('transaction_flag',1)
            ->first();

        if ($trans->transaction_count > 0){

            //update balance credit
            $install = DB::table('omf_vospay_installment')
                ->select('installment_each_amount','installment_lenght_month','installment_interest')
                ->where('installment_transaction_id',$transactionId)
                ->where('installment_flag',1)
                ->first();

            $balance = DB::table('omf_vospay_balance')
                ->select('balance_available','balance_used')
                ->where('balance_mobile_number',$trans->transaction_mobile_number)
                ->first();

            //$installmentRate = ($install->installment_interest*intval($trans->transaction_total_amount))*intval($install->installment_lenght_month;
            //$transAmount = (intval($install->installment_each_amount)-$installmentRate)*intval($install->installment_lenght_month;
			$transAmount = intval($trans->transaction_total_amount);
            $lastBalanceAvailable = intval($balance->balance_available)+intval($transAmount);
            $lastBalanceUsed = intval($balance->balance_used)-intval($transAmount);

            $detailUpdateBalance = array("balance_available"=>$lastBalanceAvailable,"balance_used"=>$lastBalanceUsed,"balance_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_balance')
                ->where('balance_mobile_number', $trans->transaction_mobile_number)
                ->update($detailUpdateBalance);

            //update transaction flag to be 2
            $detilUpdateTrans = array("transaction_flag"=>2,"transaction_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_transaction')
                ->where('transaction_id', $transactionId)
                ->update($detilUpdateTrans);

            //update transaction item refund status to be 1
            DB::table('omf_vospay_transaction_item')
                ->where('item_transaction_id', $transactionId)
                ->update(['item_refund_status' => 1]);

            //update flag installment to be 2
            $detailUpdateInst = array("installment_flag"=>2,"installment_updated"=>date('Y-m-d H:i:s'));
            DB::table('omf_vospay_installment')
                ->where('installment_transaction_id', $transactionId)
                ->update($detailUpdateInst);

            $data = $this->codeOK;
        }else{
            $data = $this->codeDDNMATCH;
        }

        return response($data,200);
    }
}