<?php

namespace App\Http\Controllers\Backend;

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
use App\Models\PackagePlan;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyController extends Controller
{
    public function AllProperty()
    {
        $property = Property::latest()->get();
        return view('backend.property.all_property', compact('property'));
    }

    public function AddProperty()
    {
        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();
        $activeAgent = User::where('status','active')->where('role','agent')->latest()->get();
        return view('backend.property.add_property', compact('propertytype','amenities','activeAgent'));
    }

    public function StoreProperty(Request $request)
    {
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
                'agent_id' => $request->agent_id,
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

        $notification = array(
            'message' => 'Property Created Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->route('all.property')->with($notification);
    }

    public function EditProperty($id)
    {
        $property = Property::findOrFail($id);
        $facilities = Facility::where('property_id',$id)->get();

        $type = $property->amenities_id;
        $property_ami = explode(',',$type);

        $multiImage = MultiImage::where('property_id',$id)->get();

        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();
        $activeAgent = User::where('status','active')->where('role','agent')->latest()->get();

        return view('backend.property.edit_property', compact('property','propertytype','amenities','activeAgent','property_ami','multiImage','facilities'));
    }

    public function UpdateProperty(Request $request)
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
                'agent_id' => $request->agent_id,
                'updated_at' => Carbon::now(),

        ]);

        $notification = array(
            'message' => 'Property Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->route('all.property')->with($notification);
    }

    public function UpdatePropertyThumbnail(Request $request)
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

    public function UpdatePropertyMultiimage(Request $request)
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

    public function PropertyMultiImageDelete($id)
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

    public function StoreNewMultiimage(Request $request)
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

    public function UpdatePropertyFacilities(Request $request)
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


    public function DeleteProperty($id)
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

    public function DetailsProperty($id)
    {
        $property = Property::findOrFail($id);
        $facilities = Facility::where('property_id',$id)->get();

        $type = $property->amenities_id;
        $property_ami = explode(',',$type);

        $multiImage = MultiImage::where('property_id',$id)->get();

        $propertytype = PropertyType::latest()->get();
        $amenities = Amenities::latest()->get();
        $activeAgent = User::where('status','active')->where('role','agent')->latest()->get();

        return view('backend.property.details_property', compact('property','propertytype','amenities','activeAgent','property_ami','multiImage','facilities'));
    }

    public function InactiveProperty(Request $request)
    {
        $pid = $request->id;
        Property::findOrFail($pid)->update([
            'status' => 0,
        ]);

        $notification = array(
            'message' => 'Property Status Updated Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('all.property')->with($notification);
    }

    public function ActiveProperty(Request $request)
    {
        $pid = $request->id;
        Property::findOrFail($pid)->update([
            'status' => 1,
        ]);

        $notification = array(
            'message' => 'Property Status Updated Successfully!',
            'alert-type' => 'success'
            );
    
        return redirect()->route('all.property')->with($notification); 
    }

    public function AdminPackageHistory()
    {
        $packagehistory = PackagePlan::latest()->get();
        return view('backend.package.package_history', compact('packagehistory'));
    }

    public function PackageInvoice($id)
    {
        $packagehistory = PackagePlan::where('id',$id)->first();

        $pdf = Pdf::loadView('backend.package.package_history_invoice', compact('packagehistory'))->setPaper('a4')->setOption([
            'tempDir' => public_path(),
            'chroot' => public_path(),
        ]);

        return $pdf->download('invoice.pdf');
    }

}
