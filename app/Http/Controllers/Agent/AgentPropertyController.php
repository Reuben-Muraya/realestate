<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\MultiImage;
use App\Models\Facility;
use App\Models\Amenities;
use App\Models\PropertyType;
use App\Models\User;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Carbon\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator; //Generate property code dynamically
use Illuminate\Support\Facades\Auth;
use DB;
use App\Models\PackagePlan;
use Barryvdh\DomPDF\Facade\Pdf;

class AgentPropertyController extends Controller
{
    public function AgentAllProperty()
    {
        $id = Auth::user()->id;
        $property = Property::where('agent_id',$id)->latest()->get();
        return view('agent.property.all_property', compact('property'));
    }

    public function AgentAddProperty()
    {
        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();

        $id = Auth::user()->id;
        $property = User::where('role','agent')->where('id',$id)->first();
        $pcount = $property->credit;
        // dd($pcount);

        if ($pcount == 1 || $pcount == 7) {
            return redirect()->route('buy.package');
        }else
        {
            return view('agent.property.add_property', compact('propertytype','amenities'));
        }

    }

    public function AgentStoreProperty(Request $request)
    {
        $id = Auth::user()->id;
        $uid = User::findOrFail($id);
        $nid = $uid->credit;

        $amen = $request->amenities_id;
        $amenities = implode(",",$amen);
        // dd($amenities);
        
        // Property code generator
        $pcode = IdGenerator::generate(['table' => 'properties','field' =>'property_code','length' => 5, 'prefix' => 'PC']);

        // if ($request->file('property_thumbnail')) {
            // $request->file('property_thumbnail');
            $manager = new ImageManager(new Driver());
            $name_gen = hexdec(uniqid()).'.'.$request->file('property_thumbnail')->getClientOriginalExtension();
            $img = $manager->read($request->file('property_thumbnail'));
            $img = $img->resize(370,250);

            $img->toJpeg(80)->save(base_path('public/upload/property/thumbnail/'.$name_gen));
            $save_url = 'upload/property/thumbnail/'.$name_gen;

            $property_id = Property::insertGetId([
                'ptype_id' => $request->ptype_id,
                'amenities_id' => $amenities,
                'property_name' => $request->property_name,
                'property_slug' => strtolower(str_replace(' ','-',$request->property_name)),
                'property_code' => $pcode,
                'property_status' => $request->property_status,
                'lowest_price' => $request->lowest_price,
                'max_price' => $request->max_price,
    
                'short_descp' => $request->short_descp,
                'long_descp' => $request->long_descp,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'garage' => $request->garage,
                'garage_size' => $request->garage_size,
    
                'property_size' => $request->property_size,
                'property_video' => $request->property_video,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
    
                'neighborhood' => $request->neighborhood,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'featured' => $request->featured,
                'hot' => $request->hot,
                'agent_id' => Auth::user()->id,
                'status' => 1,
                'property_thumbnail' => $save_url,
                'created_at' => Carbon::now(),
            ]);

        // }

        ////  Multiple Image Upload ////
        if ($files = $request->file('multi_img')){
           foreach($files as $file){

                $manager = new ImageManager(new Driver());
                $make_name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $file = $manager->read($file);
                $file = $file->resize(770,520);

                $file->toJpeg(80)->save(base_path('public/upload/property/multi-image/'.$make_name));
                $uploadPath = 'upload/property/multi-image/'.$make_name;

        MultiImage::insert([
            'property_id' => $property_id,
            'photo_name' => $uploadPath,
            'created_at' => Carbon::now(),
        ]);
        }// End foreach
    } 

        /// End Multi Image Upload ////

        /// Facility Add /////

        $facilities = Count($request->facility_name);

        if ($facilities != NULL) {
           for ($i=0; $i < $facilities; $i++) { 
               $fcount = new Facility();
               $fcount->property_id = $property_id;
               $fcount->facility_name = $request->facility_name[$i];
               $fcount->distance = $request->distance[$i];
               $fcount->save();
           }
        }

        /// End Facility Method ////
        

        //////  Credit field update on user table ///////
        User::where('id',$id)->update([
            'credit' => DB::raw('1 + '.$nid),
        ]);
        //////  End Credit field update on user table ///////

        $notification = array(
            'message' => 'Property Created Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->route('agent.all.property')->with($notification);
    }

    public function AgentEditProperty($id)
    {
        $property = Property::findOrFail($id);
        $facilities = Facility::where('property_id',$id)->get();

        $type = $property->amenities_id;
        $property_ami = explode(',',$type);

        $multiImage = MultiImage::where('property_id',$id)->get();

        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();

        return view('agent.property.edit_property', compact('property','propertytype','amenities','property_ami','multiImage','facilities'));
    }

    public function AgentUpdateProperty(Request $request)
    {
        $amen = $request->amenities_id;
        $amenities = implode(",",$amen);

        $property_id = $request->id;

        Property::findOrFail($property_id)->update([

                'ptype_id' => $request->ptype_id,
                'amenities_id' => $amenities,
                'property_name' => $request->property_name,
                'property_slug' => strtolower(str_replace(' ','-',$request->property_name)),
                'property_status' => $request->property_status,
                'lowest_price' => $request->lowest_price,
                'max_price' => $request->max_price,
    
                'short_descp' => $request->short_descp,
                'long_descp' => $request->long_descp,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'garage' => $request->garage,
                'garage_size' => $request->garage_size,
    
                'property_size' => $request->property_size,
                'property_video' => $request->property_video,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
    
                'neighborhood' => $request->neighborhood,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'featured' => $request->featured,
                'hot' => $request->hot,
                'agent_id' => Auth::user()->id,
                'updated_at' => Carbon::now(),

        ]);

        $notification = array(
            'message' => 'Property Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->route('agent.all.property')->with($notification);
    }

    public function AgentUpdatePropertyThumbnail(Request $request)
    {
        $pro_id = $request->id;
        $oldImage = $request->old_img; 

        $manager = new ImageManager(new Driver());
        $name_gen = hexdec(uniqid()).'.'.$request->file('property_thumbnail')->getClientOriginalExtension();
        $img = $manager->read($request->file('property_thumbnail'));
        $img = $img->resize(370,250);

        $img->toJpeg(80)->save(base_path('public/upload/property/thumbnail/'.$name_gen));
        $save_url = 'upload/property/thumbnail/'.$name_gen;

        if(file_exists($oldImage)) {
            unlink($oldImage);
        }

        Property::findOrFail($pro_id)->update([
            'property_thumbnail' => $save_url,
            'updated_at' => Carbon::now(),
        ]);

        $notification = array(
            'message' => 'Property Thumbnail Image Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function AgentUpdatePropertyMultiimage(Request $request)
    {
        $imgs = $request->multi_img;

        foreach($imgs as $id => $img)
        {
            $imgDel = MultiImage::findOrFail($id);
            unlink($imgDel->photo_name);


            // if ($files = $request->file('multi_img')){
                // foreach($files as $file){
     
                    $manager = new ImageManager(new Driver());
                    $make_name = hexdec(uniqid()).'.'.$img->getClientOriginalExtension();
                    $img = $manager->read($img);
                    $img = $img->resize(770,520);
     
                    $img->toJpeg(80)->save(base_path('public/upload/property/multi-image/'.$make_name));
                    $uploadPath = 'upload/property/multi-image/'.$make_name;
     
             MultiImage::where('id',$id)->update([
                 'photo_name' => $uploadPath,
                 'updated_at' => Carbon::now(),
             ]);
            //  }// End foreach
        //  } 
        }

        $notification = array(
            'message' => 'Property Multi Image Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function AgentPropertyMultiImageDelete($id)
    {
        $oldImg = MultiImage::findOrFail($id);
        unlink($oldImg->photo_name);

        MultiImage::findOrFail($id)->delete();

        $notification = array(
            'message' => 'Property Multi Image Deleted Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);

    }

    public function AgentStoreNewMultiimage(Request $request)
    {
        $new_multi = $request->imageid;
        $image = $request->file('multi_img');

        $manager = new ImageManager(new Driver());
        $make_name = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();
        $image = $manager->read($image);
        $image = $image->resize(770,520);
     
        $image->toJpeg(80)->save(base_path('public/upload/property/multi-image/'.$make_name));
        $uploadPath = 'upload/property/multi-image/'.$make_name;
     
        MultiImage::insert([
                 'property_id' => $new_multi,
                 'photo_name' => $uploadPath,
                 'created_at' => Carbon::now(),
        ]);

        $notification = array(
            'message' => 'Property Multi Image Added Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function AgentUpdatePropertyFacilities(Request $request)
    {
        $pid = $request->id;

        if($request->facility_name == NULL) 
        {
            return redirect()->back();
        }
        else
        {
            Facility::where('property_id',$pid)->delete();

            $facilities = Count($request->facility_name);

           for ($i=0; $i < $facilities; $i++) { 
               $fcount = new Facility();
               $fcount->property_id = $pid;
               $fcount->facility_name = $request->facility_name[$i];
               $fcount->distance = $request->distance[$i];
               $fcount->save();
             }// end for 
        }

        $notification = array(
            'message' => 'Property Facility Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function AgentDeleteProperty($id)
    {
        $property = Property::findOrFail($id);
        unlink($property->property_thumbnail);

        Property::findOrFail($id)->delete();

        $image = MultiImage::where('property_id',$id)->get();

        foreach($image as $img)
        {
            unlink($img->photo_name);
            MultiImage::where('property_id',$id)->delete();
        }

        $facilitiesData = Facility::where('property_id',$id)->get();
        foreach($facilitiesData as $item)
        {
            $item->facility_name;
            Facility::where('property_id',$id)->delete();
        }
        
        $notification = array(
        'message' => 'Property Deleted Successfully!',
        'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function AgentDetailsProperty($id)
    {
        $property = Property::findOrFail($id);
        $facilities = Facility::where('property_id',$id)->get();

        $type = $property->amenities_id;
        $property_ami = explode(',',$type);

        $multiImage = MultiImage::where('property_id',$id)->get();

        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();
        // $activeAgent = User::where('status','active')->where('role','agent')->latest()->get();

        return view('agent.property.details_property', compact('property','propertytype','amenities','property_ami','multiImage','facilities'));
    }

    public function AgentInactiveProperty(Request $request)
    {
        $pid = $request->id;
        Property::findOrFail($pid)->update([
            'status' => 0,
        ]);

        $notification = array(
            'message' => 'Property Status Updated Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('agent.all.property')->with($notification);
    }

    public function AgentActiveProperty(Request $request)
    {
        $pid = $request->id;
        Property::findOrFail($pid)->update([
            'status' => 1,
        ]);

        $notification = array(
            'message' => 'Property Status Updated Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('agent.all.property')->with($notification); 
    }

    public function BuyPackage()
    {
        return view('agent.package.buy_package');
    }

    public function BuyBusinessPlan()
    {
        $id = Auth::user()->id;
        $data = User::find($id);
        return view('agent.package.business_plan', compact('data'));
    }

    public function StoreBusinessPlan(Request $request)
    {
        $id = Auth::user()->id;
        $uid = User::findOrFail($id);
        $nid = $uid->credit;

        PackagePlan::insert([

            'user_id' => $id,
            'package_name' => 'Business',
            'invoice' => 'ERS'.mt_rand(10000000,99999999),
            'package_credits' => '3',
            'package_amount' => '20',
            'created_at' => Carbon::now(),
        ]);

         //////  Credit field update on user table ///////
         User::where('id',$id)->update([
            'credit' => DB::raw('3 + '.$nid),
        ]);
        //////  End Credit field update on user table ///////

        $notification = array(
            'message' => 'You have purchased Business Package Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('agent.all.property')->with($notification); 
    }
    
    public function BuyProfessionalPlan()
    {
        $id = Auth::user()->id;
        $data = User::find($id);
        return view('agent.package.professional_plan', compact('data'));
    }

    public function StoreProfessionalPlan(Request $request)
    {
        $id = Auth::user()->id;
        $uid = User::findOrFail($id);
        $nid = $uid->credit;

        PackagePlan::insert([

            'user_id' => $id,
            'package_name' => 'Professional',
            'invoice' => 'ERS'.mt_rand(10000000,99999999),
            'package_credits' => '10',
            'package_amount' => '50',
            'created_at' => Carbon::now(),
        ]);

         //////  Credit field update on user table ///////
         User::where('id',$id)->update([
            'credit' => DB::raw('10 + '.$nid),
        ]);
        //////  End Credit field update on user table ///////

        $notification = array(
            'message' => 'You have purchased Professional Package Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('agent.all.property')->with($notification); 
    }

    public function PackageHistory()
    {
        $id = Auth::user()->id;
        $packagehistory = PackagePlan::where('user_id',$id)->get();

        return view('agent.package.package_history', compact('packagehistory'));
    }

    public function AgentPackageInvoice($id)
    {
        $packagehistory = PackagePlan::where('id',$id)->first();

        $pdf = Pdf::loadView('agent.package.package_history_invoice', compact('packagehistory'))->setPaper('a4')->setOption([
            'tempDir' => public_path(),
            'chroot' => public_path(),
        ]);

        return $pdf->download('invoice.pdf');
    }
}
