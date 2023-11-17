<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Transaction;
use App\Enums\TransactionType;

class TransactionController extends Controller
{
    //create deposit
    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "amount" => "required|numeric|min:0.01",
        ]);

        try {
            DB::beginTransaction();

            $user = User::lockForUpdate()->find($request->user_id);

            if (!$user) {
                throw new \Exception("User not found.");
            }

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            $transaction = new Transaction([
                "user_id" => $user->id,
                "transaction_type" => TransactionType::CREDIT,
                "amount" => $request->amount,
                "fee" => 0.0,
                "date" => now(),
            ]);
            $user->userTransactions()->save($transaction);
            $user->balance += $request->amount;
            $user->save();

            DB::commit();

            $response = [
                "success" => true,
                "message" => "Deposit successful",
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();

            $response = [
                "success" => false,
                "message" => $e->getMessage(),
            ];
            return response()->json($response, 400); 
        }
    }
    //show users deposit
    public function showDeposits()
    {
        
        $deposits = Transaction::where("transaction_type", "credit")->get();

        // Format the transactions for JSON response
        $formattedDeposits = $this->formatDeposits($deposits);
        return response()->json(["deposits" => $formattedDeposits]);
    }
    //Formate Transaction
    private function formatDeposits($deposits)
    {
        $formattedDeposits = [];

        foreach ($deposits as $deposit) {
            $formattedDeposits[] = [
                "id" => $deposit->id,
                "user_id" => $deposit->user_id,
                "amount" => $deposit->amount,
                "date" => $deposit->created_at->format("Y-m-d H:i:s"),
            ];
        }

        return $formattedDeposits;
    }
    //Withdraw amount
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "amount" => "required|numeric|min:0.01",
        ]);
        try {
            DB::beginTransaction();

            $user = User::lockForUpdate()->find($request->user_id);

            if (!$user) {
                throw new \Exception("User not found.");
            }

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }
            $accountType = $user->account_type;

            // Calculate withdrawal fee based on account type and withdrawal amount
            $withdrawalFee = $this->calculateWithdrawalFee($accountType, $request->amount, $user);
            // Check if the user has sufficient balance (including fee)
            $totalWithdrawalAmount = $request->amount + $withdrawalFee;
            if ($user->balance < $totalWithdrawalAmount) {
                throw new \Exception("Insufficient balance.");
            }

            // Create a transaction record
            $transaction = new Transaction([
                "user_id" => $user->id,
                "transaction_type" => TransactionType::DEBIT,
                "amount" => $request->amount,
                "fee" => $withdrawalFee,
                "date" => now(),
            ]);

            $user->userTransactions()->save($transaction);
            // Deduct withdrawal amount and fee from user's balance
            $user->balance -= $totalWithdrawalAmount;
            $user->save();

            DB::commit();

            $response = [
                "success" => true,
                "message" => "Withdrawl successful",
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();

            $response = [
                "success" => false,
                "message" => $e->getMessage(),
            ];
            return response()->json($response, 400); 
        }
    }

    private function calculateWithdrawalFee($accountType, $amount, User $user)
    {
        // Apply individual account withdrawal conditions
        if ($accountType == "INDIVIDUAL") {
            // Free withdrawal on Fridays
            if (now()->dayOfWeek === 5) {
                // 5 is Friday
                return 0.0;
            }

            // Calculate the fee for the amount that exceeds 1K
            $excessAmount = max($amount - 1000, 0); // Calculate the excess amount beyond 1K
            $excessFee = 0;

            if ($excessAmount > 0) {
                // Check the total withdrawal amount for this month
                $totalWithdrawalThisMonth = $this->calculateTotalWithdrawalThisMonth($user);
                $remainingFreeWithdrawal = max(5000 - $totalWithdrawalThisMonth,0); // Remaining free up to 5K
                $chargedAmount = max($excessAmount - $remainingFreeWithdrawal,0);
                $excessFee = $chargedAmount * 0.00015;
            }
            // The total fee is the fee for the excess amount beyond 1K
            return $excessFee;
        }

        // Decrease fee to 0.015% for business accounts after 50K withdrawal
        if ($accountType == "BUSINESS") {
            $totalWithdrawal = $this->calculateTotalWithdrawal($user);
            if ($totalWithdrawal > 50000) {
                $fee = $amount * 0.00015;
            } else {
                $fee = $amount * 0.00025;
            }
            return $fee;
        }

        return 0;
    }
    //calculate withdraw for a month
    private function calculateTotalWithdrawalThisMonth(User $user)
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $totalWithdrawalThisMonth = $user
            ->userTransactions()
            ->where("transaction_type", "debit")
            ->whereBetween("date", [$startOfMonth, $endOfMonth])
            ->sum("amount");

        return $totalWithdrawalThisMonth;
    }
    //calculate total withdraw
    private function calculateTotalWithdrawal(User $user)
    {
        $totalWithdrawal = $user
            ->userTransactions()
            ->where("transaction_type", "debit")
            ->sum("amount");

        return $totalWithdrawal;
    }
    //show withdraw
    public function showWithdrawls()
    {
        $withdrawls = Transaction::where("transaction_type", "debit")->get();

        // Format the transactions for JSON response
        $formattedWithdrawls = $this->formatWithdrawls($withdrawls);
        return response()->json(["withdrawls" => $formattedWithdrawls]);
    }
    //Formate Transaction
    private function formatWithdrawls($withdrawls)
    {

        $formattedWithdrawls = [];
        foreach ($withdrawls as $withdraw) {
            $formattedWithdrawls[] = [
                "id" => $withdraw->id,
                "user_id" => $withdraw->user_id,
                "amount" => $withdraw->amount,
                "fee" => $withdraw->fee,
                "date" => $withdraw->created_at->format("Y-m-d H:i:s"),
            ];
        }

        return $formattedWithdrawls;
    }
    //show total transactions
    public function showTransactions()
    {
    // Fetch all transactions for all users
    $transactions = Transaction::all();

    // Calculate the overall bank balance
    $totalCreditAmount = Transaction::where('transaction_type', TransactionType::CREDIT)->sum('amount');
    $totalDebitAmount = Transaction::where('transaction_type', TransactionType::DEBIT)->sum('amount');
    $totalFees = Transaction::where('transaction_type', TransactionType::DEBIT)->sum('fee');

    $currentBalance = $totalCreditAmount - ($totalDebitAmount + $totalFees);
    // Hide created_at and updated_at fields
    $transactions->makeHidden(['created_at', 'updated_at']);
    $response = [
        'transactions' => $transactions,
        'current_balance' => $currentBalance,
    ];

    return response()->json($response, 200);
    }

}
