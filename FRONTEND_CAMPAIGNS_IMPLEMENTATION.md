# Frontend Campaigns Implementation Summary

## Overview

Successfully implemented campaign dropdown functionality in the frontend to work with the backend campaigns system. Users can now switch between campaigns, and all API requests automatically include the selected campaign_id.

## What Was Implemented

### 1. Campaign Store (`resources/js/stores/campaignStore.js`)

-   **State Management**: campaigns, currentCampaign, isLoading
-   **Actions**: fetchCampaigns, createCampaign, updateCampaign, deleteCampaign, switchCampaign
-   **Persistence**: Saves current campaign to localStorage
-   **Auto-initialization**: Loads campaigns and sets default campaign on first load

### 2. API Service Enhancement (`resources/js/services/api.js`)

-   **Automatic Campaign ID Injection**: Automatically adds campaign_id to POST/PUT/PATCH requests for prompts, organizations, and articles
-   **Smart Detection**: Only adds campaign_id to endpoints that need it
-   **Storage Integration**: Reads current campaign from localStorage

### 3. Navigation Component (`resources/js/components/AppNav.vue`)

-   **Campaign Dropdown**: Added campaign selector next to team selector
-   **Search Functionality**: Users can search through campaigns
-   **Visual Indicators**: Shows "Default" and "Current" labels
-   **Auto-refresh**: Page reloads when switching campaigns to ensure fresh data

## Key Features

### Campaign Dropdown

-   **Location**: Right after the teams dropdown in the navigation
-   **Styling**: Slightly different background color (bg-neutral-700) to distinguish from teams
-   **Search**: Built-in search functionality to filter campaigns
-   **Labels**: Shows "Default" for default campaigns, "Current" for selected campaign

### Automatic Campaign Context

-   **API Integration**: All relevant API requests automatically include campaign_id
-   **No Code Changes Required**: Existing stores and components work without modification
-   **Smart Routing**: Only adds campaign_id to endpoints that need it (/prompts, /organizations, /articles)

### Data Persistence

-   **localStorage**: Current campaign persists across browser sessions
-   **Auto-loading**: Campaign store initializes on app load
-   **Fallback**: Automatically selects default campaign if none is stored

## Technical Implementation

### Store Pattern

```javascript
// Campaign store follows Pinia composition API pattern
const campaignStore = useCampaignStore()
const currentCampaign = computed(() => campaignStore.currentCampaign)
```

### API Interceptor

```javascript
// Automatically adds campaign_id to relevant requests
if (shouldAddCampaignId) {
	const currentCampaign = JSON.parse(localStorage.getItem('currentCampaign') || 'null')
	if (currentCampaign && currentCampaign.id) {
		config.data.campaign_id = currentCampaign.id
	}
}
```

### Component Integration

```vue
<!-- Campaign dropdown in AppNav.vue -->
<PopoverRoot v-if="currentTeam">
    <PopoverTrigger as-child>
        <div class="flex items-center space-x-2 cursor-pointer px-3 py-1 rounded bg-neutral-700 hover:bg-neutral-600">
            <span class="text-sm font-medium">{{ currentCampaign?.name || 'Select Campaign' }}</span>
            <ChevronDownIcon />
        </div>
    </PopoverTrigger>
    <!-- Dropdown content with search and campaign list -->
</PopoverRoot>
```

## User Experience

### Campaign Selection Flow

1. User logs in and sees default campaign selected
2. User can click campaign dropdown to see all available campaigns
3. User can search campaigns by name
4. User clicks to switch campaign
5. Page refreshes with new campaign context
6. All subsequent API calls use the new campaign

### Visual Design

-   **Consistent**: Matches existing team dropdown design
-   **Distinguishable**: Different background color to differentiate from teams
-   **Intuitive**: Clear labels and hover states
-   **Responsive**: Works within existing navigation layout

## Integration Points

### Existing Stores

-   **No Changes Required**: Existing prompt, organization, and article stores work unchanged
-   **Automatic Context**: API service handles campaign_id injection transparently
-   **Future-Proof**: New stores automatically get campaign context

### Backend Compatibility

-   **Seamless Integration**: Works with backend campaign requirements
-   **Validation**: Backend validates campaign ownership through team membership
-   **Error Handling**: Proper error messages if campaign access is denied

## Next Steps for Enhancement

### Optional Improvements

1. **Real-time Updates**: Add campaign switching without page refresh
2. **Campaign Management**: Add create/edit campaign functionality to UI
3. **Campaign Analytics**: Show campaign-specific metrics in dropdown
4. **Keyboard Navigation**: Add keyboard shortcuts for campaign switching

## Testing Checklist

✅ Campaign store loads and initializes correctly
✅ Default campaign is selected on first load
✅ Campaign dropdown appears and functions
✅ Campaign switching works and persists
✅ API requests include campaign_id automatically
✅ Search functionality works in campaign dropdown
✅ Visual indicators (Default/Current) display correctly
✅ Page refresh occurs on campaign switch
