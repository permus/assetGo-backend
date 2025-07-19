# Frontend Loading State Implementation

Since the backend Laravel API is already set up, you'll need to implement loading states in your frontend application. Here's how to add loading indicators:

## 1. React Implementation Example

```jsx
import React, { useState, useEffect } from 'react';

const LocationList = () => {
  const [locations, setLocations] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchLocations = async (filters = {}) => {
    setLoading(true);
    setError(null);
    
    try {
      const queryParams = new URLSearchParams(filters).toString();
      const response = await fetch(`/api/locations?${queryParams}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      
      const data = await response.json();
      
      if (data.success) {
        setLocations(data.data.locations);
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to fetch locations');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLocations();
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-2 text-gray-600">Loading locations...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 bg-red-50 border border-red-200 rounded-md">
        <p className="text-red-600">{error}</p>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-4 text-sm text-gray-600">
        Showing {locations.length} locations
      </div>
      
      {locations.map((location) => (
        <div key={location.id} className="bg-white rounded-lg shadow p-4 mb-4">
          <div className="flex items-center">
            <div className="bg-orange-100 p-2 rounded-lg mr-3">
              <span className="text-orange-600">📍</span>
            </div>
            <div className="flex-1">
              <h3 className="font-semibold">{location.name}</h3>
              <div className="flex items-center text-sm text-gray-500">
                <span className="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs mr-2">
                  L{location.hierarchy_level}
                </span>
                <span>{location.type?.name}</span>
              </div>
              {location.address && (
                <p className="text-sm text-gray-500 mt-1">📍 {location.address}</p>
              )}
            </div>
            <div className="flex space-x-2">
              <button className="bg-blue-600 text-white px-4 py-2 rounded-md text-sm">
                View Details
              </button>
              <button className="p-2 text-gray-400 hover:text-gray-600">
                <span>⚙️</span>
              </button>
              <button className="p-2 text-gray-400 hover:text-gray-600">
                <span>✏️</span>
              </button>
              <button className="p-2 text-gray-400 hover:text-gray-600">
                <span>🗑️</span>
              </button>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default LocationList;
```

## 2. Vue.js Implementation Example

```vue
<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center p-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span class="ml-2 text-gray-600">Loading locations...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 rounded-md">
      <p class="text-red-600">{{ error }}</p>
    </div>

    <!-- Content -->
    <div v-else>
      <div class="mb-4 text-sm text-gray-600">
        Showing {{ locations.length }} locations
      </div>
      
      <div v-for="location in locations" :key="location.id" 
           class="bg-white rounded-lg shadow p-4 mb-4">
        <!-- Location card content -->
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      locations: [],
      loading: false,
      error: null,
    };
  },
  
  methods: {
    async fetchLocations(filters = {}) {
      this.loading = true;
      this.error = null;
      
      try {
        const queryParams = new URLSearchParams(filters).toString();
        const response = await this.$http.get(`/api/locations?${queryParams}`);
        
        if (response.data.success) {
          this.locations = response.data.data.locations;
        } else {
          this.error = response.data.message;
        }
      } catch (err) {
        this.error = 'Failed to fetch locations';
      } finally {
        this.loading = false;
      }
    },
  },
  
  mounted() {
    this.fetchLocations();
  },
};
</script>
```

## 3. Loading States for Different Actions

### Search/Filter Loading
```jsx
const [searchLoading, setSearchLoading] = useState(false);

const handleSearch = async (searchTerm) => {
  setSearchLoading(true);
  await fetchLocations({ search: searchTerm });
  setSearchLoading(false);
};
```

### Create Location Loading
```jsx
const [createLoading, setCreateLoading] = useState(false);

const handleCreateLocation = async (locationData) => {
  setCreateLoading(true);
  try {
    const response = await fetch('/api/locations', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(locationData),
    });
    
    if (response.ok) {
      await fetchLocations(); // Refresh list
    }
  } finally {
    setCreateLoading(false);
  }
};
```

## 4. CSS Loading Animations

```css
/* Spinner */
.spinner {
  border: 2px solid #f3f3f3;
  border-top: 2px solid #3498db;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Skeleton Loading */
.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

## 5. Backend API Response Structure

The Laravel API already returns the correct structure:

```json
{
  "success": true,
  "data": {
    "locations": [...],
    "pagination": {...},
    "filters": {...}
  }
}
```

## Implementation Steps:

1. **Add loading state variables** to your component
2. **Set loading to true** before API calls
3. **Set loading to false** after API calls complete
4. **Show loading UI** when loading is true
5. **Handle error states** appropriately
6. **Add loading indicators** for specific actions (search, create, etc.)

The backend API is already optimized and ready to handle these requests efficiently!