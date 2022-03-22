<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Providers\RouteServiceProvider;
use App\Rules\StatusTransactionChecking;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{

    private $store_payment_media_path;

    public function __construct()
    {
        $this->store_payment_media_path = RouteServiceProvider::USER_PUBLIC_ASSETS_PAYMENT_PROOF_PATH;
    }

    public function switch($status, Request $request)
    {   
        $rules = [
            'transaction_id' => 'required|exists:transactions,id',
            'status' => [
                'required', 
                'in:pending,paid',
                new StatusTransactionChecking($status)
                ]
        ];

        $validator = Validator::make($request->all() + ['status' => $status], $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::findOrFail($request->transaction_id);
            $transaction->status = $status;
            $transaction->save();

            DB::commit();
        } catch (Exception $e) {

            DB::rollBack();
            Log::error('Switch Status Transaction Issue : ['.json_encode($request->all() + ['status' => $status]).'] '.$e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to switch status transaction. Please try again.']);
        }

        return response()->json(['success' => true, 'message' => 'The transaction has been confirmed', 'data' => $transaction]);
    }

    public function index($status)
    {
        switch (strtolower($status)) {
            case "pending":
                $transaction = Transaction::where('status', 'pending')->where('payment_proof', NULL)->orderBy('created_at', 'desc')->get();
                break;

            case "need-confirmation":
                $transaction = Transaction::where('status', 'pending')->where(function($query) {
                    $query->where('payment_proof', '!=', NULL)->orWhere('payment_method', '!=', NULL)->orWhere('payment_date', '!=', NULL);
                })->orderBy('created_at', 'desc')->get();
                break;
            
            case "paid":
                $transaction = Transaction::where('status', 'paid')->orderBy('created_at', 'desc')->get();
                break;
        }
        
        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function upload_payment_proof(Request $request)
    {
        $rules = [
            'old_uploaded_file' => 'nullable|in:true,false',
            'transaction_id' => 'required|exists:transactions,id', 
            'uploaded_file' => 'required|file|max:1000|prohibited_if:old_uploaded_file,true'
        ];

        $custom_message = [
            'uploaded_file.prohibited_if' => 'You already upload the payment proof for this transaction.'
        ];

        $validator = Validator::make($request->all(), $rules, $custom_message);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('uploaded_file')) {
                $transaction = Transaction::find($request->transaction_id);

                $trx_id = $transaction->trx_id;
                $med_file_name = $trx_id;
                $med_file_format = $request->file('uploaded_file')->getClientOriginalExtension();
                $med_file_path = $request->file('uploaded_file')->storeAs($this->store_payment_media_path, $med_file_name.'.'.$med_file_format);

                $transaction->payment_proof = $med_file_path;
                $transaction->payment_method = "transfer";
                $transaction->payment_date = Carbon::now();
                $transaction->save();
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Upload Payment Proof Issue : ['.json_encode($request->all()).'] '.$e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to upload payment proof. Please try again.']);
        }

        return response()->json(['success' => true, 'message' => 'Your proof of payment has been received']);
    }
    
    public function store($data)
    {
        try {
            $transaction = new Transaction;
            $transaction->st_act_id = $data['st_act_id'];
            $transaction->amount = $data['amount'];
            $transaction->total_amount = $data['total_amount'];
            $transaction->status = $data['status'];
            $transaction->save();
            $inserted_id = $transaction->id;
            $st_act_id = $data['st_act_id'] < 10 ? '0'.$data['st_act_id'] : $data['st_act_id'];
            $student_id = $data['student_id'] < 10 ? '0'.$data['student_id'] : $data['student_id'];

            $trx_id = date('Ymd').rand(100,999).$st_act_id.rand(10, 99).$student_id.$inserted_id;
            $transaction->trx_id = $trx_id;
            $transaction->save();

            return $transaction;
        } catch (Exception $e) {
            throw New Exception($e->getMessage());
        }
    }
}
