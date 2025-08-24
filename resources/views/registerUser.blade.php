<!DOCTYPE html>
<html>

<head>
    <title>User Registration + Dropzone File Upload</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            padding: 40px;
        }

        .form-container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        input,
        button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .dropzone {
            border: 2px dashed #6366f1;
            background: #eef2ff;
            padding: 30px;
            margin-top: 20px;
        }

        .dz-message {
            font-size: 16px;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h2 style="text-align:center;">📝 User Registration</h2>

        @if (session('success'))
            <p style="color:green;">{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            <ul style="color:red;">
                @foreach ($errors->all() as $error)
                    <li>⚠️ {{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form id="mainForm" method="POST" action="{{ route('register.store') }}">
            @csrf
            <input type="text" name="name" placeholder="👤 Full Name" value="{{ old('name') }}" required />
            <input type="email" name="email" placeholder="📧 Email Address" value="{{ old('email') }}" required />

            <input type="hidden" name="uploaded_images[]" id="uploaded_images" />

            <div class="dropzone" id="dropzoneUploader">
                <div class="dz-message">📤 Drop files here or click to upload</div>
            </div>

            <button type="submit">🚀 Submit Registration</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script>
        Dropzone.autoDiscover = false;

        let uploadedImages = [];

        const myDropzone = new Dropzone("#dropzoneUploader", {
            url: "{{ route('register.upload') }}",
            maxFilesize: 50, // MB
            uploadMultiple: true,
            parallelUploads: 10,
            acceptedFiles: ".jpg,.jpeg,.png,.gif,.pdf",
            addRemoveLinks: true,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            success: function(file, response) {
                if (response && Array.isArray(response.files)) {
                    file.customNames = [];

                    response.files.forEach(fileName => {
                        if (!uploadedImages.includes(fileName)) {
                            uploadedImages.push(fileName);
                            file.customNames.push(fileName);
                        }
                    });
                }
                updateHiddenFields();
            },
            removedfile: function(file) {
                if (file.customNames && Array.isArray(file.customNames)) {
                    file.customNames.forEach(name => {
                        uploadedImages = uploadedImages.filter(img => img !== name);
                    });
                }
                updateHiddenFields();
                file.previewElement.remove();
            }
        });

        function updateHiddenFields() {
            // Clear previous hidden inputs
            document.querySelectorAll('input[name="uploaded_images[]"]').forEach(el => el.remove());

            const form = document.getElementById('mainForm');
            uploadedImages.forEach(name => {
                const input = document.createElement('input');
                input.type = "hidden";
                input.name = "uploaded_images[]";
                input.value = name;
                form.appendChild(input);
            });
        }
    </script>


</body>

</html>
