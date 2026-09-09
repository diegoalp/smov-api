<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['instance_id','business_id','document_type_id','user_id','title','file','disk','original_name','mime_type'])]
class Document extends Model {}
