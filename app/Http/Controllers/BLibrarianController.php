<?php

namespace App\Http\Controllers;

use App\Models\BookBorReq;
use App\Models\Books;
use App\Models\Member;
use App\Models\Professional;
use App\Models\Recommended;
use App\Models\Student;
use App\Models\User;
use App\Notifications\RequestNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BLibrarianController extends Controller
{
    public function home()
    {
        $borrowed_books = Books::where('status', '=', 'BORROWED')->limit(10)->get();

        $request_books = DB::table('book_bor_reqs')
        ->join('books', 'book_id', '=', 'books.id')
        ->join('members', 'member_id', '=', 'members.id')
        ->select('book_bor_reqs.status', 'books.title', 'members.firstName', 'members.lastName', 'book_bor_reqs.created_at')
        ->limit(10)
        ->get();
        
        $borrowers_app = DB::table('members')
        ->select('members.firstName', 'members.lastName', 'members.id_card', 'members.status', 'members.created_at' )
        ->limit(10)
        ->get();

        return view('borrowing_librarian.dashboard_borrowing_librarian', compact('borrowed_books', 'request_books', 'borrowers_app'));
    }

    //start borrowed books methods

    public function borrowedBooksIndex(){
        $borrowed_books = Books::where('status', '=', 'BORROWED')->get();
        return view('borrowing_librarian.borrowed_books', compact('borrowed_books'));
    }

    //end borrowed books methods



    //start requested books methods 

    public function requestedBooksIndex(){
        $request_books = DB::table('book_bor_reqs')
        ->join('books', 'book_id', '=', 'books.id')
        ->join('members', 'member_id', '=', 'members.id')
        ->select('book_bor_reqs.created_at','book_bor_reqs.id','book_bor_reqs.book_id','book_bor_reqs.member_id','book_bor_reqs.status', 'books.title', 'members.firstName', 'members.lastName')
        ->get();

        return view('borrowing_librarian.requested_books', compact('request_books'));

    }

    public function requestedBooksShow($id){
        $request_book = DB::table('book_bor_reqs')
        ->join('books', 'book_id', '=', 'books.id')
        ->join('members', 'member_id', '=', 'members.id')
        ->where('book_bor_reqs.id', '=', $id)
        ->select('book_bor_reqs.created_at', 'book_bor_reqs.id', 'book_bor_reqs.book_id', 'books.title', 'books.author', 'books.published', 
                 'books.subject', 'books.publisher', 'books.isbn', 'books.summary', 
                 'books.collection', 'books.shelf_location', 'books.status', 'members.firstName', 
                 'members.lastName', 'members.email', 'members.phone', 'members.status AS memberStatus',
                 'book_bor_reqs.status AS bookReqStatus'
                 )
        ->limit(1)
        ->get();

        return view('borrowing_librarian.show_req_book', compact('request_book'));

    }

    public function requestedBooksUpdate(Request $request, BookBorReq $book_req, Books $book){
        
        $book_req->where('id', $request->id)->update([   
            "status" => $request->status,
        ]);


        if($request->status == "APPROVED")
        {
            $bookID = BookBorReq::find($request->id);
            $books_id = Books::where('id', '=', $bookID->book_id)->get();

            foreach ($books_id as $b_id) {
                $date_time = Carbon::now()->toDateTimeString();

                $book->where('id', $b_id->id)->update([   
                    "status" => $request->statusBook,
                    "borrowed_at" => $date_time,
                ]);

                $cur_member = BookBorReq::find($request->id);

                $cur_user = Member::find($cur_member->member_id);

                $user = User::find($cur_user->user_id);

            
                $info = [
                        'info' => "Your Book Request was APPROVED: Book Title($b_id->title) at $date_time",
                        'remarks' => "Request Approved",
                        'id' => $user->id,
                ];
                

                $user->notify(new RequestNotification($info));
            }

            return redirect()->route('borrowing_librarian.requested_books.view')->with('message', 'Book borrow request was successfully approved!');
        }
        else if($request->status == "DECLINED")
        {
            $bookID = BookBorReq::find($request->id);
            $books_id = Books::where('id', '=', $bookID->book_id)->get();
            foreach ($books_id as $b_id) {
                $date_time = Carbon::now()->toDateTimeString();

                $cur_member = BookBorReq::find($request->id);

                $cur_user = Member::find($cur_member->member_id);

                $user = User::find($cur_user->user_id);

            
                $info = [
                        'info' => "Your Book Request was DECLINED: Book Title($b_id->title) at $date_time",
                        'remarks' => $request->remarks,
                        'id' => $user->id,
                ];
                

                $user->notify(new RequestNotification($info));
            }

            return redirect()->route('borrowing_librarian.requested_books.view')->with('message', 'Book borrow request was successfully declined!');
        }
    }

    //end requested books methods


    //start borrowers card application methods
    public function borrowersCardIndex(){
        $borrowers_app = Member::all();

        return view('borrowing_librarian.borrower_card_app', compact('borrowers_app'));

    }

    public function borrowersCardShow($id){
        $borrowers_app = Member::where('id', '=', $id)->limit(1)->get();

        $is_prof = Professional::where('member_id', '=', $id)->limit(1)->get();
        $is_stud = Student::where('member_id', '=', $id)->limit(1)->get();
        $is_rec = Recommended::where('member_id', '=', $id)->limit(1)->get();

        return view('borrowing_librarian.show_borrower_app', compact('borrowers_app', 'is_prof', 'is_stud', 'is_rec'));

    }

    public function borrowersCardUpdate(Request $request, Member $member){
        $member->where('id', $request->id)->update([   
            "status" => $request->status,
        ]);

        if($request->status == "APPROVED")
        {   
            $date_time = Carbon::now()->toDateTimeString();

            $cur_user = Member::find($request->id);

            $user = User::find($cur_user->user_id);

        
            $info = [
                    'info' => "Your Borrower Card Application was APPROVED: at $date_time",
                    'remarks' => "Application Approved",
                    'id' => $user->id,
            ];
            

            $user->notify(new RequestNotification($info));

            return redirect()->route('borrowing_librarian.borrower_card_app.view')->with('message', 'Borrower Card Application was successfully approved!');
        }
        else if($request->status == "DECLINED")
        {
            $date_time = Carbon::now()->toDateTimeString();

            $cur_user = Member::find($request->id);

            $user = User::find($cur_user->user_id);

        
            $info = [
                    'info' => "Your Borrower Card Application was DECLINED: at $date_time",
                    'remarks' => $request->remarks,
                    'id' => $user->id,
            ];
            

            $user->notify(new RequestNotification($info));
            return redirect()->route('borrowing_librarian.borrower_card_app.view')->with('message', 'Borrower Card Application was successfully declined!');
        }



    }

    //end borrowers card application methods

}
