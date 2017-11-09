<?php

namespace Webelightdev\LaravelMediaManager\src\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;
use Webelightdev\LaravelMediaManager\src\MediaImage;
use Webelightdev\LaravelMediaManager\src\MediaDocument;
use Intervention\Image\ImageManagerStatic as Image;

class MediaController extends Controller
{
    protected $fileSystem;
    protected $storageDisk;
    protected $mediaImage;
    protected $mediaDocument;
    protected $allowed_mimes;
    protected $image_types;
    protected $allowed_extension;

    public function __construct(MediaImage $mediaImage, MediaDocument $mediaDocument)
    {
        $this->mediaImage = $mediaImage;
        $this->mediaDocument = $mediaDocument;
        $this->fileSystem      = config('mediaManager.storage_disk');
        $this->storageDisk = app('filesystem')->disk($this->fileSystem);
        $this->allowed_mimes = config('mediaManager.allowed_mimes');
        $this->image_types = config('mediaManager.image_types');
        $this->allowed_extension = config('mediaManager.allowed_extension');
    }
    public function create()
    {
        $directoryLists = $this->storageDisk->directories();
        return view('MediaManager::media', compact('directoryLists'));
    }
    public function new_folder(Request $request)
    {
        if($this->storageDisk->exists($request->folderName)){
            return redirect('/media')->with('error', trans('MediaManager::messages.folder_exists_already'));
        }else {
            foreach ( $this->image_types as $key => $imageType) {
                $this->storageDisk->makeDirectory($request->folderName.'/images/'."/$imageType/");
            }
            $this->storageDisk->makeDirectory($request->folderName.'/documents/');
            return redirect('/media')->with('success', trans('MediaManager::messages.create_new_folder'));
        }
    }
    public function store(Request $request)
    {
        $upload_path = $request->upload_path;
        $files       = $request->file('photos');
        $imageVarients = $request->images;
        if($files && $upload_path){
            foreach ($files as $file){
                try{
                    //Check For mime and Extension
                    $file_name = $file->getClientOriginalName();
                    $file_type = $file->getMimeType();
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    if (!(str_contains($file_type, $this->allowed_mimes) && str_contains($file_extension, $this->allowed_extension))) {
                        throw new Exception(trans('MediaManager::messages.not_allowed_file_ext'));
                    }
                    $this->imagesResize($imageVarients, $file, $this->image_types, $upload_path);
                } catch (Exception $e) {
                    $result[] = [
                        'success' => false,
                        'message' => "\"$file_name\" " . $e->getMessage(),
                    ]
                }
            }
        } else {
            dd('sdfsd');
        }

        if($request->file('documents')){
            $originalDocuments = $request->file('documents');

            if($request->directory_lists){
                $directoryName = $request->directory_lists;
            }
            $this->storageDisk->makeDirectory($directoryName.'/documents/');
            $this->storeDocuments($originalDocuments, $directoryName);
           
        }
    }


    public function imagesResize($imageVarients, $originalImages, $imageTypes, $upload_path)
    {
        foreach ($originalImages as $originalImage) {
            $orignalImageName = $originalImage->getClientOriginalName();
            $newImage = Image::make($originalImage);
            $newImage->backup();
            foreach ($this->image_types as $key => $imageType) {
                $newImage->reset()->resize($imageVarients[$imageType]['img_width'], $imageVarients[$imageType]['img_height'], function ($constraint) {
                       $constraint->aspectRatio();
                    });
                if(array_has($imageVarients[$imageType], 'include_canvas') && $imageVarients[$imageType]['include_canvas'] == 1){
                    $newImage->resizeCanvas($imageVarients[$imageType]['img_canvas_width'], $imageVarients[$imageType]['img_canvas_height'], 'center', false, $imageVarients[$imageType]['img_canvas_color']);
                }
                $newImage->save();
                $this->storageDisk->put($directoryName.'/images/'."/$imageType/".$orignalImageName, $newImage);
            }
            DB::beginTransaction();
            try{
                $this->mediaImage->create([
                    'name' => $orignalImageName,
                    'original_path' =>$directoryName.'/images/original/',
                    'medium_path' =>$directoryName.'/images/medium/',
                    'small_path' =>$directoryName.'/images/small/',
                    'extraSmall_path' =>$directoryName.'/images/extra_small/',
                ]);
              } catch (\Illuminate\Database\QueryException $e) {
                DB::rollback();
                return Response::json($e->getMessage() , 422);
            }
            DB::commit();
            return Response::json("Data Saved SuccessFully", 200);
        }
    }

    public function storeDocuments($originalDocuments, $directoryName)
    {
       foreach ($originalDocuments as $originalDocument) {
           $originalDocumentName = $originalDocument->getClientOriginalName();
           $temp = explode(".", $originalDocumentName);
           $documentType = end($temp);
           $allowedExts = array();
           $path = $directoryName.'/documents/';
           if(in_array($documentType, $allowedExts)){
               $this->storageDisk->put($directoryName.'/documents/'.$originalDocumentName, File::get($originalDocument));
            } else {
                //Error
            }
            DB::beginTransaction();
            try{
                $this->mediaDocument->create([
                    'name' => $originalDocumentName,
                    'path' =>$path,
                ]);
              } catch (\Illuminate\Database\QueryException $e) {
                DB::rollback();
                return Response::json($e->getMessage() , 422);
            }
            DB::commit();
            return Response::json("Data Saved SuccessFully", 200);
        }
    }
}
